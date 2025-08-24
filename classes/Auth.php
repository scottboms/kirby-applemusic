<?php
declare(strict_types=1);

namespace Scottboms\MusicKit;

use Firebase\JWT\JWT;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Http\Response;

class Auth
{
	/**
	 * musickit config status
	 * @return Array
	 */
	public static function musickit_config_status(): array
	{
		$opts = [
			'teamId'     => option('scottboms.applemusic.teamId'),
			'keyId'      => option('scottboms.applemusic.keyId'),
			'privateKey' => option('scottboms.applemusic.privateKey'),
		];
		return self::configStatus($opts);
	}


	/**
	 * config status
	 * @return Array
	 */
	public static function configStatus(array $opts): array
	{
		$missing = [];
		foreach (['teamId','keyId','privateKey'] as $k) {
			if (empty($opts[$k])) $missing[] = $k;
		}

		$errors = [];
		// validate formats if present (don't "warn" on empty)
		if (!empty($opts['teamId']) && !\preg_match('/^[A-Z0-9]{10}$/', $opts['teamId'])) {
			$errors[] = 'teamId must be 10 uppercase letters/numbers';
		}
		if (!empty($opts['keyId']) && !\preg_match('/^[A-Z0-9]{10}$/', $opts['keyId'])) {
			$errors[] = 'keyId must be 10 uppercase letters/numbers';
		}
		if (!empty($opts['privateKey']) && \strpos($opts['privateKey'], 'BEGIN PRIVATE KEY') === false) {
			$errors[] = 'privateKey must be a valid PEM string';
		}

		$ok = empty($missing) && empty($errors);
		$status = $ok ? 'ok' : (!empty($missing) ? 'unconfigured' : 'invalid');

		return [
			'ok'      => $ok,
			'status'  => $status,  // ok | unconfigured | invalid
			'missing' => $missing, // e.g. teamId | keyId
			'errors'  => $errors,  // value format issues only
		];
	}


	/**
	 * validate required apple keys in options
	 * return response on error, null on success
	 * @return Json
	 */
	public static function validateOptions(array $opts): ?Response
	{
		$status = static::configStatus($opts);
		if ($status['ok']) return null;

		// keep endpoints strict, but respond with structured payload
		return Response::json([
			'error'   => 'Invalid configuration',
			'status'  => $status['status'], // unconfigured | invalid
			'missing' => $status['missing'],
			'errors'  => $status['errors'],
		], 400);
	}


	/**
	 * minutes to keep cached user token (default: 30 days)
	 * @return Integer
	 */
	public static function tokenCacheTtl(): int
	{
		$minutes = (int) option('scottboms.applemusic.tokenCacheTtlMinutes', 60 * 24 * 30);
		return max(1, $minutes);
	}


	/**
	 * set domain host for cache
	 * @return String
	 */
	private static function domainFolder(): string
	{
		// use kirby request host if available
		$url  = kirby()->request()->url();
		$host = $url ? $url->host() : null;

		// fallback: base url host
		if (!$host) {
			$base = kirby()->url('base');
			$host = $base ? parse_url($base, PHP_URL_HOST) : 'unknown';
		}

		// sanitise for filesystem
		$host = strtolower($host);
		return preg_replace('~[^a-z0-9\.\-]+~', '-', $host);
	}


	/**
	 * path to persist the per-user music-user-token
	 * @return String
	 */
	public static function tokenPath(?string $userId = null): string
	{
		$uid = $userId ?? (kirby()->user()?->id() ?? 'site');
		$root  = kirby()->root('cache');
		$dom   = static::domainFolder();
		return $root . '/' . $dom . '/scottboms/applemusic/' . $uid . '.json';
	}


	/**
	 * store the music-user-token
	 * @return Boolean
	 */
	public static function storeToken(string $token, ?string $userId = null): bool
	{
		$path = static::tokenPath($userId);
		Dir::make(\dirname($path), true);

		$payload = ['musicUserToken' => $token, 'updatedAt' => \date('c')];
		$ok = F::write($path, \json_encode($payload, JSON_PRETTY_PRINT)) !== false;

		if ($ok) {
			MusicKit::cache()->set(
			MusicKit::cacheKey('token:' . \basename($path, '.json')),
				$token,
				static::tokenCacheTtl()
			);
		}
		return $ok;
	}


	/**
	 * read the music-user-token (cache -> file fallback)
	 * @return String
	 */
	public static function readToken(?string $userId = null): ?string
	{
		$uid   = $userId ?? (kirby()->user()?->id() ?? 'site');
		$key   = MusicKit::cacheKey('token:' . $uid);
		$cache = MusicKit::cache();

		if ($cached = $cache->get($key)) {
			return \is_string($cached) ? $cached : null;
		}

		$path = static::tokenPath($userId);
		if (!F::exists($path)) return null;

		$json  = \json_decode(F::read($path), true);
		$token = $json['musicUserToken'] ?? null;

		if (\is_string($token) && $token !== '') {
			$cache->set($key, $token, static::tokenCacheTtl());
			return $token;
		}
		return null;
	}


	/**
	 * delete cached token
	 * @return Boolean
	 */
	public static function deleteToken(?string $userId = null): bool
	{
		$uid  = $userId ?? (kirby()->user()?->id() ?? 'site');
		$key  = MusicKit::cacheKey('token:' . $uid);
		$path = static::tokenPath($userId);

		MusicKit::cache()->remove($key);
		return F::exists($path) ? F::remove($path) : true;
	}


	/**
	 * mint a developer jwt (throws on invalid inputs)
	 * uses jwt library
	 * @return String
	 */
	public static function mintDevToken(array $opts): string
	{
		$now = \time();
		$payload = [
			'iss' => $opts['teamId'],
			'iat' => $now,
			'exp' => $now + (int)($opts['tokenTtl'] ?? 3600),
		];
		return JWT::encode($payload, $opts['privateKey'], 'ES256', $opts['keyId']);
	}


	/**
	 * cached developer token (mint if absent)
	 * @return String
	 */
	public static function devToken(array $opts): string
	{
		// scoped by credentials and domain to avoid collisions
		$team  = (string)($opts['teamId'] ?? 'noteam');
		$keyId = (string)($opts['keyId']  ?? 'nokey');
		$dom   = static::domainFolder();
		$cacheKey = MusicKit::cacheKey("dev_token:{$team}:{$keyId}:{$dom}");

		$cache = MusicKit::cache();
		if ($token = $cache->get($cacheKey)) {
			return $token;
		}

		// sign a fresh jwt
		$ttl = max(300, (int)($opts['tokenTtl'] ?? 3600)); // >= 5 min
		$jwt = static::mintDevToken($opts);

		// cache a bit shorter than 'exp' to avoid edge cases/clock skew
		$buffer   = min(120, (int)round($ttl * 0.10)); // 10% or up to 120s
		$cacheTtl = max(1, $ttl - $buffer);
		$cache->set($cacheKey, $jwt, $cacheTtl);
		return $jwt;
	}


	/**
	 * refresh dev token
	 * @return String
	 */
	public static function refreshDevToken(array $opts): string
	{
		$team  = (string)($opts['teamId'] ?? 'noteam');
		$keyId = (string)($opts['keyId']  ?? 'nokey');
		$dom   = static::domainFolder();
		$cacheKey = MusicKit::cacheKey("dev_token:{$team}:{$keyId}:{$dom}");

		MusicKit::cache()->remove($cacheKey);
		return static::devToken($opts);
	}

	/**
	 * cors for dev-token endpoint
	 * @return Array
	 */
	public static function devTokenCorsHeaders(?string $origin, array $allowedOrigins): array
	{
		$headers = ['Content-Type' => 'application/json'];
		if ($origin && \in_array($origin, $allowedOrigins, true)) {
			$headers['Access-Control-Allow-Origin'] = $origin;
			$headers['Vary'] = 'Origin';
		}
		return $headers;
	}


  /**
	 * user must be logged into the panel
	 * @return String
	 */
	public static function ensurePanelUser()
	{
		if (!$user = kirby()->user()) {
			return Response::json(['status' => 'error', 'message' => 'Unauthorized'], 401);
		}
		return $user;
	}


	/**
	 * must have stored music-user-token
	 * @return String
	 */
	public static function ensureUserToken(string $userId)
	{
		$mut = static::readToken($userId);
		if (!$mut) {
			return Response::json(['status' => 'error', 'message' => 'Missing Music-User-Token'], 403);
		}
		return $mut;
	}


	/**
	 * render the apple music auth page
	 * $sf can be 'auto' or a storefront code (eg. 'us')
	 * will be html-escaped.
	 * $appName/$appBuild are injected as json for MusicKit.configure({ app: { name, build } })
	 * @return Json
	 */
	public static function renderAuthPage(string $sf, string $appName, string $appBuild): Response
	{
		$sfLower = htmlspecialchars(strtolower($sf), ENT_QUOTES, 'UTF-8');

		$path = dirname(__DIR__) . '/src/assets/auth.html';
		if (!F::exists($path)) {
			return Response::json([
				'status'  => 'error',
				'message' => 'Auth template not found',
				'path'    => $path
			], 500);
		}

		$html = F::read($path);

		$html = strtr($html, [
			'{{sfLower}}'      => $sfLower,
			'{{appNameJson}}'  => json_encode($appName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			'{{appBuildJson}}' => json_encode($appBuild, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
		]);
		return new Response(trim($html), 'text/html');
	}


	/**
	 * return any saved music-user-token
	 * priority: configured default user id -> first token file found
	 * @return String
	 */
	public static function readAnyToken(): ?string
	{
		// return a valid token
		$dir = \dirname(static::tokenPath('any'));
		if (!Dir::exists($dir)) {
			return null;
		}

		foreach (Dir::files($dir) as $file) {
			if (\substr($file, -5) !== '.json') {
				continue;
			}
			$path  = $dir . '/' . $file;
			$json  = \json_decode(F::read($path) ?: 'null', true);
			$token = $json['musicUserToken'] ?? null;

			if (is_string($token) && $token !== '') {
				// mirror into cache for future reads
				$uid = \basename($file, '.json');
				MusicKit::cache()->set(MusicKit::cacheKey('token:' . $uid), $token, self::tokenCacheTtl());
				return $token;
			}
		}
		return null;
	}

}
