<template>
	<k-panel-inside>
		<k-view class="k-musickit-config-view">
			<k-header class="k-site-view-header">
				Configure Apple Music

				<template #buttons>
					<k-button-group>
						<k-button
							variant="filled"
							icon="document"
							size="sm"
							link="https://github.com/scottboms/kirby-applemusic/blob/main/README.md"
							target="_blank"
							text="Setup Guide"
						/>
					</k-button-group>
				</template>
			</k-header>

			<k-box v-if="loading" icon="loader">
				Checking configuration...
			</k-box>

			<template v-else>
				<k-box
					v-if="localStatus !== 'ok'"
					icon="settings"
					:text="emptyText"
					theme="warning"
				/>

				<k-box
					v-if="localStatus !== 'ok' && (localMissing.length || localErrors.length)"
					theme="text"
					style="margin-top: var(--spacing-2)"
				>
					<k-text theme="text">
						<h2>Errors</h2>

						<ul>
							<li v-if="localMissing.length">Missing required configuration settings — <strong>{{ localMissing.join(', ') }}</strong>
							</li>

						<li v-for="(error, index) in localErrors" :key="index">{{ error }}</li>
						</ul>

						<p>Correct these settings in <code>env.php</code>
							under <code>scottboms.applemusic.*</code>.
							Review the Setup Guide for further information.</p>
					</k-text>
				</k-box>
			</template>
		</k-view>
	</k-panel-inside>
</template>

<script>
export default {
	name: 'MusicKitConfig',

	props: {
		appName: String,
		appBuild: String,
		hasToken: Boolean,
		status: String,
		missing: Array,
		errors: Array
	},

	data() {
		return {
			loading: true,
			localStatus: this.status ?? null,
			localMissing: this.missing ?? [],
			localErrors: this.errors ?? []
		};
	},

	computed: {
		emptyText() {
			return this.localStatus === 'unconfigured'
			? 'Apple Music is not fully configured yet'
			: 'Invalid Apple Music configuration';
		}
	},

	async mounted() {
		await this.checkConfiguration();
	},

	methods: {
		async checkConfiguration() {
			this.loading = true;

			try {
				const response = await fetch('/applemusic/config-status', {
					credentials: 'same-origin',
					headers: {
						Accept: 'application/json'
					}
				});

				if (!response.ok) {
					throw new Error(
						`Configuration request failed (${response.status})`
					);
				}

				const data = await response.json();

				this.localStatus = data.status ?? 'error';
				this.localMissing = data.missing ?? [];
				this.localErrors = data.errors ?? [];

			} catch (error) {
				console.error('[MusicKitConfig]', error);

				this.localStatus = 'error';
				this.localMissing = [];
				this.localErrors = [
					'Unable to check Apple Music configuration.',
					error.message
				];

			} finally {
				this.loading = false;
			}
		}
	}
};
</script>
