import { reactive } from 'vue';

/**
 * Status sibuk untuk tombol muat ulang/cek ulang. Ikon tetap berputar minimal
 * satu putaran walau server menjawab cepat, agar klik terlihat bekerja.
 *
 *   router.reload({ only: ['rows'], ...busy.track('inbox') })
 *   <RefreshCw :class="{ 'animate-spin': busy.is('inbox') }" />
 */
export function useBusy(minMs = 800) {
    const keys = reactive(new Set<string | number>());

    return {
        is: (key: string | number) => keys.has(key),
        track(key: string | number) {
            let started = 0;

            return {
                onStart: () => {
                    started = Date.now();
                    keys.add(key);
                },
                onFinish: () => {
                    setTimeout(
                        () => keys.delete(key),
                        Math.max(0, minMs - (Date.now() - started)),
                    );
                },
            };
        },
    };
}
