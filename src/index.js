import AppleMusicField from "./components/AppleMusicField.vue";
import AppleMusicBlock from "./components/AppleMusicBlock.vue";
import MusicKitConfig  from "./components/MusicKitConfig.vue";
import MusicKitHistory from "./components/MusicKitHistory.vue";

import { icons }       from "./icons.js";

panel.plugin("scottboms/kirby-applemusic", {
	icons,
	components: {
		"k-musickit-config-view": MusicKitConfig,
		"k-musickit-history-view": MusicKitHistory,
	},
	fields: {
		applemusic: AppleMusicField
	},
	blocks: {
		applemusic: AppleMusicBlock
	}
});
