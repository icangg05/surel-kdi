<script setup lang="ts">
import { useId } from 'vue';

defineOptions({ inheritAttrs: false });

// Id mask harus unik: tanda bisa tampil lebih dari sekali di satu halaman.
const maskId = `tanda-${useId().replace(/[^\w-]/g, '')}`;
</script>

<template>
    <!--
      Tanda Surel Kendari: amplop bercap emas. Amplop mengikuti currentColor
      (biru malam di latar terang, langit di latar biru malam); lipatan dan
      celah di sekeliling cap dipotong transparan agar latar ikut terlihat.
    -->
    <svg v-bind="$attrs" viewBox="0 0 64 64" fill="none" aria-hidden="true">
        <defs>
            <mask
                :id="maskId"
                maskUnits="userSpaceOnUse"
                x="0"
                y="0"
                width="64"
                height="64"
            >
                <rect width="64" height="64" fill="#fff" />
                <path
                    d="M10.5 19.5 32 35l21.5-15.5"
                    stroke="#000"
                    stroke-width="3.4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <circle cx="32" cy="35" r="11.6" fill="#000" />
            </mask>
        </defs>
        <rect
            x="6"
            y="15"
            width="52"
            height="36"
            rx="6.5"
            fill="currentColor"
            :mask="`url(#${maskId})`"
        />
        <circle cx="32" cy="35" r="9" fill="#f2b705" />
        <circle cx="32" cy="35" r="5.8" stroke="#9a6a00" stroke-width="1.5" />
    </svg>
</template>
