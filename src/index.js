import AppleMusicField from "./components/AppleMusicField.vue";
import AppleMusicBlock from "./components/AppleMusicBlock.vue";
import MusicKitConfig  from "./components/MusicKitConfig.vue";
import MusicKitHistory from "./components/MusicKitHistory.vue";
import MusicKitSong    from "./components/MusicKitSong.vue";
import MusicKitAlbum   from "./components/MusicKitAlbum.vue";

import { icons }       from "./icons.js";

panel.plugin("scottboms/kirby-applemusic", {
	icons,
	components: {
		"k-musickit-config-view": MusicKitConfig,
		"k-musickit-history-view": MusicKitHistory,
		"k-musickit-song-view": MusicKitSong,
		"k-musickit-album-view": MusicKitAlbum,
	},
	fields: {
		applemusic: AppleMusicField
	},
	blocks: {
		applemusic: AppleMusicBlock
	}
});
