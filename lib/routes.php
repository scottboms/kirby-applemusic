<?php

declare(strict_types=1);

use Kirby\Http\Response;
use Scottboms\MusicKit\MusicKit;
use Scottboms\MusicKit\Auth;

return [
	// configuration status checks
	[
		'pattern' => 'applemusic/config-status',
		'method'  => 'GET',
		'action'  => function () {
			$opts = [
				'teamId'     => option('scottboms.applemusic.teamId'),
				'keyId'      => option('scottboms.applemusic.keyId'),
				'privateKey' => option('scottboms.applemusic.privateKey'),
			];

			$cfg = Auth::configStatus($opts);
			if (!is_array($cfg)) {
				$cfg = [
					'ok'      => (bool)$cfg,
					'status'  => $cfg ? 'ok' : 'unconfigured',
					'missing' => [],
					'errors'  => [],
				];
			}

			// enrich the payload if helpful to the ui
			return \Kirby\Http\Response::json([
				'status'  => $cfg['status'],
				'missing' => $cfg['missing'],
				'errors'  => $cfg['errors'],
			], 200);
		}
	],

	// returns a developer token with cors + cache via Auth::devToken
	[
		'pattern' => 'applemusic/dev-token',
		'method'  => 'GET',
		'action'  => function () {
			$opts = MusicKit::ensureOptions();
			if ($opts instanceof Response) return $opts;

			$origin  = kirby()->request()->header('Origin');
			$headers = Auth::devTokenCorsHeaders($origin, (array)($opts['allowedOrigins'] ?? []));

			$token = Auth::devToken($opts);
			return new Response(json_encode(['token' => $token]), 'application/json', 200, $headers);
		}
	],

	// store music-user-token
	[
		'pattern' => 'applemusic/store-user-token',
		'method'  => 'POST',
		'action'  => function () {
			if (!$user = kirby()->user()) {
				return Response::json(['status' => 'error', 'message' => 'Unauthorized'], 401);
			}

			$req   = kirby()->request();
			$token = null;

			if (stripos($req->header('Content-Type') ?? '', 'application/json') !== false) {
				$json = json_decode($req->body(), true);
				$token = is_array($json) ? ($json['token'] ?? null) : null;
			}
			$token ??= $req->get('token');
			$token ??= $req->header('Music-User-Token') ?? $req->header('X-Apple-Music-User-Token');
			$token ??= get('token');

			if (!is_string($token) || strlen($token) < 32) {
				return Response::json(['status' => 'error', 'message' => 'Missing or invalid token'], 400);
			}

			if (!Auth::storeToken($token, $user->id())) {
				return Response::json(['status' => 'error', 'message' => 'Failed to store token'], 500);
			}

			return Response::json(['status' => 'ok', 'path' => Auth::tokenPath($user->id())], 200);
		}
	],

	// has token
	[
		'pattern' => 'applemusic/has-token',
		'method'  => 'GET',
		'action'  => fn () =>
			Response::json(['hasToken' => (bool) (kirby()->user() ? Auth::readToken(kirby()->user()->id()) : false)], 200)
	],

	// debug: music-user-token status route
	[
		'pattern' => 'applemusic/token-status',
		'method'  => 'GET',
		'action'  => function () {
			if (!$user = kirby()->user()) {
				return Response::json(['ok' => false, 'reason' => 'unauthorized'], 401);
			}
			$token = Auth::readToken($user->id());
			return Response::json([
				'ok'       => (bool) $token,
				'hasToken' => (bool) $token,
				'cacheKey' => MusicKit::cacheKey('token:' . $user->id()),
				'path'     => Auth::tokenPath($user->id())
			], 200);
		}
	],

	// refresh dev token
	[
		'pattern' => 'applemusic/dev-token/refresh',
		'method'  => 'POST',
		'action'  => function () {
			$opts = MusicKit::ensureOptions();
			if ($opts instanceof Response) return $opts;

			$origin  = kirby()->request()->header('Origin');
			$headers = Auth::devTokenCorsHeaders($origin, (array)($opts['allowedOrigins'] ?? []));

			// bust and mint a new dev token using the scoped cache key
			$token = Auth::refreshDevToken($opts);

			return new Response(json_encode(['token' => $token]), 'application/json', 200, $headers);
		}
	],

	// delete token route
	[
		'pattern' => 'applemusic/delete-user-token',
		'method'  => 'POST',
		'action'  => function () {
			if (!$user = kirby()->user()) {
				return Response::json(['status' => 'error', 'message' => 'Unauthorized'], 401);
			}
			if (!Auth::deleteToken($user->id())) {
				return Response::json(['status' => 'error', 'message' => 'Failed to delete token'], 500);
			}
			return Response::json(['status' => 'ok'], 200);
		}
	],

	// get storefront
	[
		'pattern' => 'applemusic/storefront',
		'method'  => 'GET',
		'action'  => fn () =>
			MusicKit::storefront(MusicKit::opts(), get('language') ?: 'en-US'),
	],

	// get recent tracks from the api
	[
		'pattern' => 'applemusic/recent',
		'method'  => 'GET',
		'action'  => function () {
			$opts = MusicKit::opts();
			$params = [
				'limit'      => (int) (get('limit') ?? option('scottboms.applemusic.songsLimit', 15)),
				'offset'     => (int) (get('offset') ?? 0),
				'language'   => get('language')   ?: 'en-US',
				'storefront' => get('storefront') ?: 'us',
			];
			return MusicKit::recentlyPlayed($opts, $params);
		}
	],

	// get details for an individual track from the api
	[
		'pattern' => 'applemusic/song/(:any)',
		'method'  => 'GET',
		'action'  => function (string $songId) {
			$language = get('l', 'en-US');
			// ensure options exist (no user token required for catalog lookups)
			$opts = MusicKit::opts();
			if ($err = Auth::validateOptions($opts)) {
				return $err; // 4xx with structured json
			}
			$res = MusicKit::songDetails($songId, $language);
			return $res;
		}
	],

	// storefront (delegates to MusicKit::storefront)
	[
		'pattern' => 'applemusic/applemusic',
		'method'  => 'GET',
		'action'  => fn () => MusicKit::storefront(MusicKit::opts(), get('language') ?: 'en-US'),
	],

	// apple music api auth route
	[
		'pattern' => 'applemusic/auth',
		'method'  => 'GET',
		'action'  => function () {
			$sf       = get('sf') ?? option('scottboms.applemusic.storefront') ?? 'auto';
			$plugin   = kirby()->plugin('scottboms/applemusic');
			$appName  = 'KirbyMusicKit';
			$appBuild = $plugin->info()['version'] ?? 'dev';

			return Auth::renderAuthPage($sf, $appName, $appBuild);
		}
	],

];
