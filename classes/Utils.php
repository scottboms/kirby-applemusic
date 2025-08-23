<?php
declare(strict_types=1);

namespace Scottboms\MusicKit;

class Utils
{
	/**
	 * helper: get only first genre from array
	 * @return String
	 */
	public static function firstGenre($genres): ?string
	{
		return (is_array($genres) && isset($genres[0]) && is_string($genres[0]))
			? $genres[0]
			: null;
	}


	/**
	 * helper: human date format
	 * @return String
	 */
	public static function humanDate(?string $iso): ?string
	{
		if (!$iso) return null;

			// full date: yyyy-mm-dd
			if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso)) {
				$dt = \DateTime::createFromFormat('Y-m-d', $iso);
				return $dt ? $dt->format('F j, Y') : $iso;
			}

			// year-month: yyyy-mm
			if (\preg_match('/^\d{4}-\d{2}$/', $iso)) {
				$dt = \DateTime::createFromFormat('Y-m', $iso);
				return $dt ? $dt->format('F Y') : $iso;
			}

			// year-only: yyyy
			if (\preg_match('/^\d{4}$/', $iso)) {
				return $iso;
			}

			// fallback: return as-is if format is unexpected
			return $iso;
	}


	/**
	 * helper: milliseconds to mm:ss
	 * @return String
	 */
	public static function format_mmss(?int $ms): ?string
	{
		if ($ms === null) return null;
		$totalSeconds = (int) floor($ms / 1000); // song timings typically floor
		$m = intdiv($totalSeconds, 60);
		$s = $totalSeconds % 60;
		return sprintf('%d:%02d', $m, $s);
	}


	/**
	 * helper: milliseconds -> human text with rounding (hours/minutes/seconds)
	 * @return String
	 */
	public static function format_human(?int $ms): ?string
	{
		if ($ms === null) return null;

		// start by rounding to the nearest second
		$totalSeconds = (int) round($ms / 1000);
		$h = intdiv($totalSeconds, 3600);
		$rem = $totalSeconds % 3600;
		$m = intdiv($rem, 60);
		$s = $rem % 60;

		// round seconds up into minutes at >= 30s
		if ($s >= 30) {
			$m++;
			$s = 0;
		}

		// carry minutes into hours
		if ($m >= 60) {
			$h += intdiv($m, 60);
			$m = $m % 60;
		}

		$parts = [];

		if ($h > 0) {
			$parts[] = $h . ' hour' . ($h > 1 ? 's' : '');
		}

		if ($m > 0) {
			$parts[] = $m . ' minute' . ($m > 1 ? 's' : '');
		}

		// simplified rule: only show seconds if minutes are zero after rounding
		// this yields examples like 1 hour, 23 seconds or just 23 seconds
		if ($m === 0 && $s > 0) {
			$parts[] = $s . ' second' . ($s > 1 ? 's' : '');
		}

		if (empty($parts)) {
			// happens for < 0.5s after rounding; choose the friendliest fallback
			return '0 minutes';
		}

		return implode(', ', $parts);
	}

}
