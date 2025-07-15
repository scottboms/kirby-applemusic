import AppleMusicField from "./components/AppleMusicField.vue";
import { icons } from "./icons.js";

panel.plugin("scottboms/kirby-applemusic", {
	icons,
	fields: {
		applemusic: AppleMusicField
	}
});
