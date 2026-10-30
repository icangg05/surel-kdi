<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <!-- Varian kecil = judul bagian di dalam kartu, dipisah garis bawah. -->
    <header :class="variant === 'small' ? 'border-b pb-3' : 'mb-8 space-y-0.5'">
        <h2
            :class="
                variant === 'small'
                    ? 'mb-0.5 text-sm font-semibold'
                    : 'text-xl font-semibold tracking-tight text-balance'
            "
        >
            {{ title }}
        </h2>
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>
    </header>
</template>
