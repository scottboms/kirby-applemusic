<?php
declare(strict_types=1);

namespace Scottboms\MusicKit;

use Scottboms\MusicKit\Auth;
use Scottboms\MusicKit\Utils;
use Kirby\Cms\Content;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Http\Response;
use Kirby\Http\Remote;

class MusicKit
{
	private const CACHE_NAMESPACE = 'scottboms.applemusic';
	private const CACHE_KEY_PREFIX = 'applemusic:';

	/**
	 * cache + cacheKey stay here (auth reuses them)
	 * @return String
	 */
	public static function cache(): \Kirby\Cache\Cache
	{
		return kirby()->cache(self::CACHE_NAMESPACE);
	}


	/**
	 * unique cacheKey prefix
	 * @return String
	 */
	public static function cacheKey(string $suffix): string
	{
		return self::CACHE_KEY_PREFIX . $suffix;
	}


	/**
	 * read plugin config options
	 * @return Array
	 */
	public static function opts(): array
	{
		$o = option('scottboms.applemusic') ?? [];
		$o['tokenTtl'] = $o['tokenTtl'] ?? option('scottboms.applemusic.devTokenTtl', 3600);
		return $o;
	}


	/**
	 * ensure options are present (delegates validation to auth)
	 * @return Array
	 */
	public static function ensureOptions(?array $opts = null)
	{
		$opts ??= static::opts();
		if ($err = Auth::validateOptions($opts)) {
			return $err;
		}
		return $opts;
	}


	/**
	 * low-level get wrapper for apple music api
	 * @return Array
	 */
	public static function appleGet(string $path, string $devToken, string $musicUserToken, array $headers = []): Response
	{
		$res = Remote::get('https://api.music.apple.com' . $path, [
			'headers' => array_replace([
				'Authorization'     => 'Bearer ' . $devToken,
				'Music-User-Token'  => $musicUserToken,
				'Accept'            => 'application/json',
			], $headers),
		]);

		$code = $res->code();
		$body = json_decode($res->content() ?? '', true);

		if ($code >= 400) {
			return Response::json([
				'status'  => 'error',
				'message' => 'Apple Music API error',
				'code'    => $code,
				'body'    => $body,
			], $code);
		}
		return Response::json($body ?? ['data' => []], 200);
	}


	/**
	 * fetch recently played tracks for the current user.
	 * @param array $opts ['teamId','keyId','privateKey','tokenTtl'?]
	 * @param array $params ['limit'=>10, 'offset'=>0, 'language'=>'en-US', 'storefront'=>'us']
	 * @param string | null $userId
	 * @return array | Response
	*/
  public static function recentlyPlayedTracks(array $opts, array $params = [], ?string $userId = null)
	{
		// validate options & ensure we have a music user token
		if ($err = Auth::validateOptions($opts)) {
			return $err;
		}

		$musicUserToken = Auth::readToken($userId);
		if (!$musicUserToken) {
			return Response::json(['error' => 'Missing music-user-token (user not authorized yet)'], 401);
		}

		// mint (or re-mint) a developer token
		$devToken = Auth::mintDevToken($opts);

		// build query
		$query = [
			'limit'  => (string)($params['limit']   ?? 15),
			'offset' => (string)($params['offset']  ?? 0),
		];

		if (!empty($params['language'])) $query['l'] = $params['language']; // e.g. "en-us"

		// tracks endpoint returns only songs, not playlists or stations
		$res = Remote::get('https://api.music.apple.com/v1/me/recent/played/tracks', [
			'headers' => [
				'Authorization'    => 'Bearer ' . $devToken,
				'Music-User-Token' => $musicUserToken,
				'Accept'           => 'application/json',
			],
			'data'    => $query,
			'timeout' => 10,
		]);

		if ($res->code() >= 400) {
			return Response::json([
				'error'  => 'Apple Music API error',
				'status' => $res->code(),
				'body'   => \json_decode($res->content(), true),
			], $res->code());
		}
		// return decoded json (api returns "data" + paging "next"/"prev" urls)
		return \json_decode($res->content(), true);
	}


	/**
	 * get user storefront
	 * @return String
	*/
	public static function storefront(array $opts, string $language = 'en-US'): Response
	{
		$user = Auth::ensurePanelUser();
		if ($user instanceof Response) return $user;

		$opts = static::ensureOptions($opts);
		if ($opts instanceof Response) return $opts;

		$mut = Auth::ensureUserToken($user->id());
		if ($mut instanceof Response) return $mut;

		$dev = Auth::devToken($opts);

		return static::appleGet('/v1/me/storefront', $dev, $mut, [
			'Accept-Language' => $language,
		]);
	}


	/**
	 * get recently played tracks
	 * keeps existing recentlyPlayedTracks() logic
	 * normalizes the return to response
	 * @return Array
	 */
	public static function recentlyPlayed(array $opts, array $params = []): Response
	{
		$result = static::recentlyPlayedTracks($opts, $params, null);
		if ($result instanceof Response) return $result;

		$data = $result['data'] ?? [];
		$data = array_map(function ($i) {
			$a = $i['attributes'] ?? [];
			if (!empty($a['artwork']['url']) && !empty($i['id'])) {
				$local = static::localCoverFromApple('song', (string)$i['id'], (string)$a['artwork']['url'], 240, 240);
				if ($local) {
					// overwrite template string
					$i['attributes']['artwork']['url'] = $local;
					// optional: also provide a flat field
					$i['image'] = $local;
				}
			}
			// clear external url for internal ids
			if (isset($i['id']) && is_string($i['id']) && str_starts_with($i['id'], 'i.')) {
				$i['attributes']['url'] = null;
			}
			return $i;
		}, $data);
		$result['data'] = $data;
		return Response::json($result, 200);
	}

	// get individual track details
	public static function songDetails(string $songId, string $language = 'en-US'): Response
	{
		$opts = static::opts();
		$dev  = Auth::devToken($opts);

		// option > user's storefront (if available) > 'us'
		$storefront = option('scottboms.applemusic.storefront');
		if ($storefront === 'auto' || empty($storefront)) {
			$sf = 'us';
			$mut = Auth::readAnyToken(); // optional
			if ($mut) {
				$sfRes = static::appleGet('/v1/me/storefront', $dev, $mut, ['Accept-Language' => $language]);
				$sfJson = json_decode($sfRes->body() ?? 'null', true);

				if (is_array($sfJson) && !empty($sfJson['data'][0]['id'])) {
					$sf = $sfJson['data'][0]['id'];
				}
			}
			$storefront = $sf ?: 'us';
		}

		// call catalog: dev token only (no music-user-token necessary)
		$resp = Remote::get('https://api.music.apple.com/v1/catalog/' . rawurlencode($storefront) . '/songs/' . rawurlencode($songId), [
			'headers' => [
				'Authorization'    => 'Bearer ' . $dev,
				'Accept'           => 'application/json',
				'Accept-Language'  => $language,
			],
			'timeout' => 10,
		]);

		$code = $resp->code();
		$body = json_decode($resp->content() ?? 'null', true);

		if ($code >= 400 || !is_array($body)) {
			return Response::json([
				'status'  => 'error',
				'message' => 'Apple Music catalog error',
				'code'    => $code,
				'body'    => $body,
			], $code ?: 500);
		}

		// normalization for component view
		$it = $body['data'][0] ?? null;
		$a  = $it['attributes'] ?? [];
		$img = null;

		if (!empty($a['artwork']['url']) && !empty($it['id'])) {
			$img = static::localCoverFromApple('song', (string)$it['id'], (string)$a['artwork']['url'], 600, 600) ?? null;
		}

		$duration = isset($a['durationInMillis'])
			? Utils::format_mmss((int)$a['durationInMillis'])
			: null;

		// releaseYear from releaseDate
		$releaseDate = $a['releaseDate'] ?? null;
		$releaseYear = null;
		if ($releaseDate) {
			$ts = strtotime($releaseDate);
			if ($ts !== false) {
				$releaseYear = date('Y', $ts);
			}
		}

		$id = $it['id'] ?? null;
		$url = $a['url'] ?? null;

		// if the id starts with "i.", clear the url
		if (is_string($id) && str_starts_with($id, 'i.')) {
			$url = null;
		}

		return Response::json([
			'id'           => $id,
			'name'         => $a['name'] ?? '',
			'artistName'   => $a['artistName'] ?? '',
			'albumName'    => $a['albumName'] ?? '',
			'composerName' => $a['composerName'] ?? '',
			'genreName'    => Utils::firstGenre($a['genreNames'] ?? null),
			'releaseDate'  => $releaseDate,
			'releaseYear'  => $releaseYear,
			'url'          => $url,
			'previewUrl'   => $a['previews'][0]['url'] ?? null,
			'duration'     => $duration,
			'image'        => $img,
			//'raw'        => $body, // optional: full response payload
		], 200);
	}


	/**
	 * get individual album details
	 * @return Array
	 */
	public static function albumDetails(string $albumId, string $language = 'en-US'): Response
	{
		$opts = static::opts();
		$dev  = Auth::devToken($opts);

		// option > user's storefront (if available) > 'us'
		$storefront = option('scottboms.applemusic.storefront');
		if ($storefront === 'auto' || empty($storefront)) {
			$sf = 'us';
			$mut = Auth::readAnyToken(); // optional
			if ($mut) {
				$sfRes = static::appleGet('/v1/me/storefront', $dev, $mut, ['Accept-Language' => $language]);
				$sfJson = json_decode($sfRes->body() ?? 'null', true);

				if (is_array($sfJson) && !empty($sfJson['data'][0]['id'])) {
					$sf = $sfJson['data'][0]['id'];
				}
			}
			$storefront = $sf ?: 'us';
		}

		// call catalog: dev token only (no music-user-token necessary)
		$resp = Remote::get('https://api.music.apple.com/v1/catalog/' . rawurlencode($storefront) . '/albums/' . rawurlencode($albumId), [
			'headers' => [
				'Authorization'    => 'Bearer ' . $dev,
				'Accept'           => 'application/json',
				'Accept-Language'  => $language,
			],
			'timeout' => 10,
		]);

		$code = $resp->code();
		$body = json_decode($resp->content() ?? 'null', true);

		if ($code >= 400 || !is_array($body)) {
			return Response::json([
				'status'  => 'error',
				'message' => 'Apple Music catalog error',
				'code'    => $code,
				'body'    => $body,
			], $code ?: 500);
		}

		// normalization for component view
		$it = $body['data'][0] ?? null;
		$a  = $it['attributes'] ?? [];
		$img = null;

		if (!empty($a['artwork']['url']) && !empty($it['id'])) {
			$img = static::localCoverFromApple('album', (string)$it['id'], (string)$a['artwork']['url'], 600, 600) ?? null;
		}

		$seconds = isset($a['durationInMillis']) ? (int) floor($a['durationInMillis'] / 1000) : null;
		$duration = is_int($seconds) ? sprintf('%d:%02d', floor($seconds/60), $seconds % 60) : null;

		// releaseYear from releaseDate
		$releaseDate = $a['releaseDate'] ?? null;
		$releaseYear = null;
		if ($releaseDate) {
			$ts = strtotime($releaseDate);
			if ($ts !== false) {
				$releaseYear = date('Y', $ts);
			}
		}

		$id = $it['id'] ?? null;
		$url = $a['url'] ?? null;

		// if the id starts with "i.", clear the url
		if (is_string($id) && str_starts_with($id, 'i.')) {
			$url = null;
		}

		// albums tracks data
		$tracksRaw = $body['data'][0]['relationships']['tracks']['data'] ?? [];

		// compute album-level "isDigitalMaster" based on any track
		$albumIsDigitalMaster = false;
		foreach ($tracksRaw as $t) {
			$ta = $t['attributes'] ?? [];
			if (!empty($ta['isAppleDigitalMaster'])) {
				$albumIsDigitalMaster = true;
				break;
			}
		}

		// build normalized tracks list
		$tracks = array_map(function ($t) {
			$a = $t['attributes'] ?? [];
			$ms = $a['durationInMillis'] ?? null;
			return [
				'id'         => $t['id'] ?? null,
				'number'     => $a['trackNumber'] ?? null,
				'name'       => $a['name'] ?? '',
				'durationMs' => $ms,
				'duration'   => Utils::format_mmss($ms),
				'url'        => $a['url'] ?? null,
			];
		}, $tracksRaw);

		// total duration
		$totalDurationMs = array_sum(array_map(fn($t) => $t['durationMs'] ?? 0, $tracks));
		$totalDuration   = Utils::format_human($totalDurationMs);

		return Response::json([
			'id'                   => $id,
			'name'                 => $a['name'] ?? '',
			'artistName'           => $a['artistName'] ?? '',
			'genreName'            => Utils::firstGenre($a['genreNames'] ?? null),
			'isMasteredForItunes'  => (bool)($a['isMasteredForItunes'] ?? false),
			'isAppleDigitalMaster' => $albumIsDigitalMaster,
			'contentRating'        => $a['contentRating'] ?? '',
			'releaseDate'          => $releaseDate,
			'releaseDateFormatted' => Utils::humanDate($releaseDate),
			'releaseYear'          => $releaseYear,
			'url'                  => $url,
			'image'                => $img,
			'recordLabel'          => $a['recordLabel'],
			'copyright'            => $a['copyright'],
			'trackCount'           => $a['trackCount'],
			'totalDuration'        => $totalDuration,
			'tracks'               => $tracks,
			//'raw'                => $body, // optional: full response payload
		], 200);
	}


	/**
	 * server-side helper for front-end snippet
	 * fetches recently played using shared token, caches response,
	 * and normalize to a render-friendly array
	 *
	 * @return Array
	 */
	public static function recentForFrontend(
		int $limit = 12,
		string $language = 'en-US',
		int $cacheTtl = 120,
		bool $asContent = true,
		int $thumbW = 240,
		int $thumbH = 240): array
	{
		$cache     = kirby()->cache('scottboms.applemusic');
		$cacheKey  = 'am:recent:site:' . md5(json_encode([$limit, $language, $thumbW, $thumbH]));

		// try cache (always arrays)
		if ($cacheTtl > 0 && ($cached = $cache->get($cacheKey))) {
			return static::toContentPayload($cached);
		}

		// need a shared (site-level) Music-User-Token
		$mut = Auth::readAnyToken();
		if (!$mut) {
			$payload = ['items' => [], 'error' => 'Missing shared Music-User-Token (site)'];
			if ($cacheTtl > 0) $cache->set($cacheKey, $payload, $cacheTtl);
			return static::toContentPayload($payload);
		}

		// dev token + fetch from api
		$opts = static::opts();
		$dev  = Auth::devToken($opts);

		$path = '/v1/me/recent/played/tracks?limit=' . (int)$limit . '&l=' . rawurlencode($language);
		$res  = static::appleGet($path, $dev, $mut);
		$json = json_decode($res->body() ?? 'null', true);

		if (!is_array($json) || !isset($json['data'])) {
			$payload = ['items' => [], 'error' => 'Apple Music API error'];
			if ($cacheTtl > 0) $cache->set($cacheKey, $payload, $cacheTtl);
			return static::toContentPayload($payload);
		}

		// normalize items to cache-friendly arrays
		$items = array_map(function ($i) use ($thumbW, $thumbH) {
			$a   = $i['attributes'] ?? [];
			$template = (string)($a['artwork']['url'] ?? '');
			$id       = (string)($i['id'] ?? '');
			$img      = null;

			// eager local (download on first view)
			if ($template !== '' && $id !== '') {
				$img = static::localCoverFromApple('song', $id, $template, $thumbW, $thumbH) ?? null;
			}

			// lazy prefer-local (no download) + fallback chain
			$thumb = static::artworkUrlPreferLocal('song', $id, $template, $thumbW, $thumbH)
				?? static::missingArtworkUrl()
				?? null;

			$url = $a['url'] ?? null;
			if ($id !== '' && str_starts_with($id, 'i.')) {
				$url = null; // internal ids shouldn't link out
			}

			return [
				'id'          => $i['id'] ?? null,
				'name'        => $a['name'] ?? '',
				'artist'      => $a['artistName'] ?? '',
				'album'       => $a['albumName'] ?? '',
				'duration'    => Utils::format_mmss($a['durationInMillis'] ?? null),
				'releaseDate' => $a['releaseDate'] ?? null, // ISO 8601 (use ->toDate() in templates)
				'url'         => $url,
				'image'       => $img,			// eager local (may be null on first run)
				'thumb'       => $thumb,    // always-resolved display image with graceful fallbacks
				'artwork'     => $template, // e.g. ".../{w}x{h}bb.jpg"
			];
		}, $json['data'] ?? []);

		$payload = ['items' => $items, 'error' => null];

		// cache arrays
		if ($cacheTtl > 0) {
			$cache->set($cacheKey, $payload, $cacheTtl);
		}

		return static::toContentPayload($payload);
	}


	/**
	 * convert ['items' => [array...]] to ['items' => [Content...]]
	 * @return Array
	 */
	protected static function toContentPayload(array $payload): array
	{
		$payload['items'] = array_map(fn ($i) => new Content($i), $payload['items'] ?? []);
		return $payload;
	}


	/**
	 * Resolve the media dir + url base
	 * @return Array
	 */
	protected static function coverStorage(): array
	{
		$base = 'scottboms/applemusic/covers';
		$root = kirby()->root('media') . '/' . $base;
		$url  = kirby()->url('media')  . '/' . $base;

		Dir::make($root, true);
		return [$root, $url];
	}


	/**
	 * deletes all cached cover files and recreates folder
	 * returns the number of files removed
	 * @return Int
	 */
	public static function clearCoverStorage(): int
	{
		[$root] = [static::coverStorage()[0]];
		if (!Dir::exists($root)) {
			Dir::make($root, true);
			return 0;
		}

		$count = 0;
		foreach ((array)Dir::read($root) as $name) {
			$path = $root . '/' . $name;
			// only touch files
			if (is_file($path) && F::remove($path)) {
				$count++;
			}
		}
		return $count;
	}


	/**
	 * build a deterministic filename for a given artwork
	 * uses the item type + id + WxH to avoid collisions
	 * covers $type 'song | album'
	 * @return String
	 */
	protected static function coverFilename(string $type, string $id, int $w, int $h, string $ext = 'jpg'): string
	{
		// sanitize - only need stable url-safe inputs
		$cleanId = preg_replace('![^a-zA-Z0-9._-]+!', '-', $id) ?? $id;
		return sprintf('%s-%s-%dx%d.%s', $type, $cleanId, $w, $h, $ext);
	}


	/**
	 * given an apple artwork template url and desired WxH, return a local media url
	 * downloads and stores the image on first use; afterwards serves from /media
	 *
	 * @param string $type 'song' or 'album' (used in filename only)
	 * @param string $id   apple music id (used in filename only)
	 * @param string $artworkTemplate e.g. "https://.../{w}x{h}bb.jpg"
	 * @param int    $w
	 * @param int    $h
	 * @param bool   $forceRefresh if true, re-download even if the file exists
	 */
	protected static function localCoverFromApple(string $type, string $id, string $artworkTemplate, int $w = 600, int $h = 600, bool $forceRefresh = false): ?string
	{
		if ($artworkTemplate === '') {
			return static::missingArtworkUrl();
		}

		// build the remote url by replacing size placeholders
		$remoteUrl = str_replace(['{w}', '{h}'], [$w, $h], $artworkTemplate);

		// guess extension from the template, fall back to mime if needed
		$ext = pathinfo(parse_url($remoteUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';

		[$root, $baseUrl] = static::coverStorage();
		$filename = static::coverFilename($type, $id, $w, $h, $ext);
		$path     = $root . '/' . $filename;
		$url      = $baseUrl . '/' . $filename;

		// if already available, return a local url
		if (!$forceRefresh && F::exists($path)) {
			return $url;
		}

		// fetch from apple
		$res = Remote::get($remoteUrl, [
			'timeout' => 10,
			'headers' => [
				'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8'
			],
		]);

		if ($res->code() >= 400) {
			// something went wrong, return fallback image
			return static::missingArtworkUrl();
		}

		$body = $res->content();
		if ($body === null || $body === '') {
			return static::missingArtworkUrl();
		}

		// if mime suggests a different extension, switch accordingly
		$mime = strtolower($res->header('Content-Type') ?? '');
		$mimeToExt = [
			'image/jpeg' => 'jpg',
			'image/jpg'  => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
			'image/avif' => 'avif',
		];

		if (isset($mimeToExt[$mime]) && $mimeToExt[$mime] !== $ext) {
			$ext      = $mimeToExt[$mime];
			$filename = static::coverFilename($type, $id, $w, $h, $ext);
			$path     = $root . '/' . $filename;
			$url      = $baseUrl . '/' . $filename;
		}

		// write file to media storage
		F::write($path, $body);
		return $url;
	}


	/*
	 * build apple url from its {w}/{h} template without fetching
	 * @return String
	 */
	public static function appleArtworkUrl(string $template, int $w, int $h): ?string
	{
		if ($template === '') return null;
		return str_replace(['{w}', '{h}'], [$w, $h], $template);
	}


	/*
	 * get local cover file if exists in media folder
	 * @return String
	 */
	public static function coverLocalIfExists(string $type, string $id, int $w, int $h): ?string
	{
		[$root, $baseUrl] = static::coverStorage();
		$cleanId = preg_replace('![^a-zA-Z0-9._-]+!', '-', $id) ?? $id;
		$base = sprintf('%s-%s-%dx%d', $type, $cleanId, $w, $h);
		foreach (['jpg','png','webp','avif'] as $ext) {
			$path = $root . '/' . $base . '.' . $ext;
			if (is_file($path)) {
				return $baseUrl . '/' . $base . '.' . $ext;
			}
		}
		return null;
	}


	/**
	 * prefer local media but if missing fall back to apple url
	 */
	public static function artworkUrlPreferLocal( string $type, string $id, string $artworkTemplate, int $w = 600, int $h = 600): ?string {
		return static::coverLocalIfExists($type, $id, $w, $h)
			?? static::appleArtworkUrl($artworkTemplate, $w, $h);
	}


	/**
	 * url to fallback image
	 * @return String
	 */
	protected static function missingArtworkUrl(): ?string
	{
		// option a: publish a shipped asset into media on first use:
		[$root, $baseUrl] = static::coverStorage();
		$filename = 'missing-artwork.jpg';
		$path     = $root . '/' . $filename;
		$url      = $baseUrl . '/' . $filename;

		if (!F::exists($path)) {
			$pluginAsset = kirby()->root('plugins') . '/kirby-applemusic/assets/missing-artwork.jpg';
			if (F::exists($pluginAsset)) {
				F::copy($pluginAsset, $path);
			} else {
				// nothing to serve
				return null;
			}
		}
		return $url;
	}

}
