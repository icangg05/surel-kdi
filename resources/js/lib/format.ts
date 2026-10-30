import {
    format,
    formatDistanceToNowStrict,
    isThisYear,
    isToday,
    isYesterday,
    parseISO,
} from 'date-fns';
import { id } from 'date-fns/locale';

const parse = (value: string | null | undefined) =>
    value ? parseISO(value) : null;

/** Tanggal ringkas untuk daftar: "14.05", "Kemarin", "28 Okt", "28 Okt 2025". */
export function shortDate(value: string | null | undefined): string {
    const d = parse(value);

    if (!d) {
        return '';
    }

    if (isToday(d)) {
        return format(d, 'HH.mm');
    }

    if (isYesterday(d)) {
        return 'Kemarin';
    }

    return format(d, isThisYear(d) ? 'd MMM' : 'd MMM yyyy', { locale: id });
}

/** "Kamis, 29 Oktober 2026 pukul 14.05" */
export function longDate(value: string | null | undefined): string {
    const d = parse(value);

    return d
        ? format(d, "EEEE, d MMMM yyyy 'pukul' HH.mm", { locale: id })
        : '';
}

export function dateOnly(value: string | null | undefined): string {
    const d = parse(value);

    return d ? format(d, 'EEEE, d MMM yyyy', { locale: id }) : '';
}

export function relative(value: string | null | undefined): string {
    const d = parse(value);

    return d
        ? formatDistanceToNowStrict(d, { addSuffix: true, locale: id })
        : '';
}

export function fileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function number(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
}

export function initials(name: string | null | undefined): string {
    return (name ?? '?')
        .replace(/[^\p{L}\s]/gu, ' ')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]!.toUpperCase())
        .join('');
}

/** Warna avatar stabil per alamat, dari palet netral-berwarna yang lembut. */
export function avatarTone(seed: string): string {
    const tones = [
        'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
        'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200',
        'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200',
        'bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200',
    ];
    let hash = 0;

    for (const ch of seed) {
        hash = (hash * 31 + ch.charCodeAt(0)) >>> 0;
    }

    return tones[hash % tones.length]!;
}

export function groupTone(color: string): string {
    const map: Record<string, string> = {
        slate: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
        blue: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
        emerald:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
        amber: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
        rose: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200',
        violet: 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200',
        cyan: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200',
        orange: 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-200',
    };

    return map[color] ?? map.slate!;
}

export function groupDot(color: string): string {
    const map: Record<string, string> = {
        slate: 'bg-slate-400',
        blue: 'bg-blue-500',
        emerald: 'bg-emerald-500',
        amber: 'bg-amber-500',
        rose: 'bg-rose-500',
        violet: 'bg-violet-500',
        cyan: 'bg-cyan-500',
        orange: 'bg-orange-500',
    };

    return map[color] ?? 'bg-slate-400';
}
