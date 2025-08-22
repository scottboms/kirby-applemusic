<?php

declare(strict_types=1);

use Kirby\Http\Response;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;
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

	// get details for an individual track from the api
	[
		'pattern' => 'applemusic/album/(:any)',
		'method'  => 'GET',
		'action'  => function (string $albumId) {
			$language = get('l', 'en-US');
			// ensure options exist (no user token required for catalog lookups)
			$opts = MusicKit::opts();
			if ($err = Auth::validateOptions($opts)) {
				return $err; // 4xx with structured json
			}
			$res = MusicKit::albumDetails($albumId, $language);
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

	// search
	[
	  'pattern' => 'applemusic/search',
	  'method'  => 'GET',
	  'action'  => function () {
			$q        = trim((string) (get('q') ?? ''));
			$limit    = max(1, min((int) (get('limit') ?? 10), 25));
			$language = get('language') ?: 'en-US';
			$sfRaw    = strtolower((string) (get('sf') ?? option('scottboms.applemusic.storefront') ?? 'us'));
			$sf       = ($sfRaw === 'auto' || !preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $sfRaw)) ? 'us' : $sfRaw;

			// type param with default
			$type = strtolower((string)(get('type') ?? 'songs'));
			if (!in_array($type, ['songs', 'albums'], true)) {
				$type = 'songs';
			}

			if ($q === '' || mb_strlen($q) < 2) {
				return Response::json(['ok' => false, 'error' => 'Query must be at least 2 characters'], 400);
			}

			$opts = MusicKit::opts();
			if ($err = Auth::validateOptions($opts)) {
				return $err; // structured 4xx json
			}

			$devToken = Auth::devToken($opts);
			if (!$devToken) {
				return Response::json(['ok' => false, 'error' => 'Developer token is not configured'], 500);
			}

			$url = 'https://api.music.apple.com/v1/catalog/' . rawurlencode($sf) . '/search';
			$qs  = http_build_query([
				'term'  => $q,
				'types' => $type, // songs | albums
				'limit' => $limit,
				'l'     => $language,
			], '', '&', PHP_QUERY_RFC3986);

			try {
				$res = Remote::get($url . '?' . $qs, [
					'headers' => [
						'Authorization' => 'Bearer ' . $devToken,
						'Accept'        => 'application/json',
					],
					'timeout' => 7,
				]);

				if ($res->code() < 200 || $res->code() >= 300) {
					return Response::json(['ok' => false, 'error' => 'Apple Music search failed (HTTP ' . $res->code() . ')'], 502);
				}

				$json  = $res->json();

				$artThumb = function (?array $art, int $size = 60): ?string {
					if (!is_array($art) || empty($art['url'])) return null;
					return str_replace(['{w}', '{h}'], [$size, $size], $art['url']);
				};

				$normalizeSong = function (array $track) use ($artThumb) {
					$id   = A::get($track, 'id');
					$attr = A::get($track, 'attributes', []);
					$name = A::get($attr, 'name', 'Untitled');
					$artist = A::get($attr, 'artistName', '');
					$date = A::get($attr, 'releaseDate', null);
					$year = null;
					if ($date && ($ts = strtotime($date)) !== false) $year = date('Y', $ts);
					return [
						'id'    => $id,
						'text'  => $name . ' - ' . $artist,
						'info'  => $year,
						'attr'  => $attr,
						'image' => $artThumb(A::get($attr, 'artwork')),
						'link'  => 'applemusic/song/' . $id,
						'kind'  => 'songs',
					];
				};

				$normalizeAlbum = function (array $album) use ($artThumb) {
					$id   = A::get($album, 'id');
					$attr = A::get($album, 'attributes', []);
					$name = A::get($attr, 'name', 'Untitled');
					$artist = A::get($attr, 'artistName', '');
					$date = A::get($attr, 'releaseDate', null);
					$year = null;
					if ($date && ($ts = strtotime($date)) !== false) $year = date('Y', $ts);

					return [
						'id'    => $id,
						'text'  => $name . ' — ' . $artist,
						'info'  => $year,
						'attr'  => $attr,
						'image' => $artThumb(A::get($attr, 'artwork')),
						// add a dedicated album-details route later
						// 'link' => 'applemusic/album/' . $id,
						'link'  => A::get($attr, 'url', null), // apple music canonical album url
						'kind'  => 'albums',
					];
				};

				// pick the correct type and normalizer
				if ($type === 'albums') {
					$items   = A::get($json, 'results.albums.data', []);
					$results = array_map($normalizeAlbum, $items);
				} else {
					$items   = A::get($json, 'results.songs.data', []);
					$results = array_map($normalizeSong, $items);
				}

				return Response::json([
					'ok'         => true,
					'results'    => $results,
					'count'      => count($results),
					'type'       => $type,
					'storefront' => $sf,
					'language'   => $language,
				], 200);

			} catch (\Throwable $e) {
				return Response::json(['ok' => false, 'error' => 'Search error: ' . $e->getMessage()], 500);
			}
		}
	],

];
