<?php

use Scottboms\MusicKit\MusicKit;

$limit    = (int)($limit ?? option('scottboms.applemusic.songsToShow', 6));
$language = $language ?? 'en-US';
$cacheTtl = (int)($cacheTtl ?? 120);

$result = MusicKit::recentForFrontend($limit, $language, $cacheTtl);
$items  = $result['items'];
$error  = $result['error'];
?>

<section class="am-recently-played">
	<h2>Recently Played</h2>

	<?php if ($error && empty($items)): ?>
		<p class="am-empty"><?= html($error) ?></p>
	<?php elseif (empty($items)): ?>
		<p class="am-empty">No items.</p>
	<?php else: ?>
		<ul class="am-grid">
			<?php foreach ($items as $t): ?>
				<li class="am-card">
					<a<?= $t['url'] ? ' href="' . html($t['url']) . '" target="_blank" rel="noopener"' : '' ?>>
						<?php if ($t['image']): ?>
							<figure>
								<img src="<?= html($t['image']) ?>" alt="<?= html($t['name']) ?>" loading="lazy">
							</figure>
						<?php endif; ?>
						<span class="am-title"><?= html($t['name']) ?></span>
						<span class="am-subtitle"><?= html($t['album']) ?></span>
						<span class="am-sub"><?= html($t['artist']) ?></span>
						<span class="am-sub"><?= html($t['duration']) ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<style>
.am-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:1rem; padding:0; list-style:none; }
.am-card a { display:block; text-decoration:none; color:inherit; }
.am-title { display:block; font-weight:600; margin-top:.5rem; }
.am-sub { display:block; color:#666; font-size:.9em; }
.am-empty { color:#666; }
.am-card figure {
	position: relative;
	width: 100%;
	padding-top: 100%; /* makes it a square box */
	overflow: hidden;
}
.am-card img {
	position: absolute;
	top: 0; left: 0;
	max-width:100%;
	height: auto;
	object-fit: cover; /* crop/cover */
	display: block;
}
</style>
