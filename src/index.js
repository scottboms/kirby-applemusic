import AppleMusicField from "./components/AppleMusicField.vue";
import AppleMusicBlock from "./components/AppleMusicBlock.vue";
import MusicKit from "./components/MusicKit.vue";

import { icons } from "./icons.js";

panel.plugin("scottboms/kirby-applemusic", {
	icons,
	components: {
		"k-musickit-view": MusicKit,
	},
	fields: {
		applemusic: AppleMusicField
	},
	blocks: {
		applemusic: AppleMusicBlock
	}
});
