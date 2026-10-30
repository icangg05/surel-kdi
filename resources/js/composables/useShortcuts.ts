import { onBeforeUnmount, onMounted } from 'vue';

type Handlers = Record<string, (e: KeyboardEvent) => void>;

/**
 * Pintasan papan ketik satu huruf (gaya Gmail). Diabaikan saat fokus di
 * kolom isian atau editor, dan saat tombol pengubah ditekan.
 */
export function useShortcuts(handlers: Handlers) {
    function onKeydown(e: KeyboardEvent) {
        const target = e.target as HTMLElement | null;

        if (
            e.ctrlKey ||
            e.metaKey ||
            e.altKey ||
            target?.closest(
                'input, textarea, select, [contenteditable="true"], [role="dialog"]',
            )
        ) {
            return;
        }

        const handler = handlers[e.key];

        if (handler) {
            e.preventDefault();
            handler(e);
        }
    }

    onMounted(() => window.addEventListener('keydown', onKeydown));
    onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
}
