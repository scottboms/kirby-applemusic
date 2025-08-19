import AppleMusicField from "./components/AppleMusicField.vue";
import AppleMusicBlock from "./components/AppleMusicBlock.vue";
import MusicKit        from "./components/MusicKit.vue";
import MusicKitConfig  from "./components/MusicKitConfig.vue";

import { icons }       from "./icons.js";

panel.plugin("scottboms/kirby-applemusic", {
	icons,
	components: {
		"k-musickit-view": MusicKit,
		"k-musickit-config-view": MusicKitConfig,
	},
	fields: {
		applemusic: AppleMusicField
	},
	blocks: {
		applemusic: AppleMusicBlock
	}
});
