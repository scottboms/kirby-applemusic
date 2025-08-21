export function makeTrackOptions({ url, onCopy, onEmbed, onError }) {
	if (!url) return [];
	return [
		{
			icon: 'headphones',
			text: 'Listen',
			click: () => window.open(url, '_blank')
		},
		'-',
		{
			icon: 'url',
			text: 'Copy Link',
			click: () => onCopy?.(url)
		},
		{
			icon: 'code',
			text: 'Embed Code',
			click: () => {
				try {
					const embed = onEmbed?.(url);
					if (!embed) throw new Error('No embed');
					onCopy?.(embed, 'Embed copied to clipboard');
				} catch (e) {
					onError?.('Could not create embed code');
				}
			}
		}
	];
}
