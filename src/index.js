import AppleMusicField from "./components/AppleMusicField.vue";
import AppleMusicBlock from "./components/AppleMusicBlock.vue";
import MusicKitConfig  from "./components/MusicKitConfig.vue";
import MusicKitHistory from "./components/MusicKitHistory.vue";
import MusicKitSong    from "./components/MusicKitSong.vue";

import { icons }       from "./icons.js";

panel.plugin("scottboms/kirby-applemusic", {
	icons,
	components: {
		"k-musickit-config-view": MusicKitConfig,
		"k-musickit-history-view": MusicKitHistory,
		"k-musickit-song-view": MusicKitSong,
	},
	fields: {
		applemusic: AppleMusicField
	},
	blocks: {
		applemusic: AppleMusicBlock
	}
});
