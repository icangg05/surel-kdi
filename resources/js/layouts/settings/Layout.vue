<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { KeyRound, SunMoon, UserRound } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const tabs: NavItem[] = [
    { title: 'Profil', href: editProfile(), icon: UserRound },
    { title: 'Keamanan', href: editSecurity(), icon: KeyRound },
    { title: 'Tampilan', href: editAppearance(), icon: SunMoon },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="mx-auto w-full max-w-4xl space-y-5 p-4 sm:p-6">
        <PageHeader
            title="Pengaturan akun"
            description="Profil, kata sandi, autentikasi dua langkah, dan tampilan."
        />

        <nav
            class="flex gap-1 overflow-x-auto border-b"
            aria-label="Pengaturan akun"
        >
            <Link
                v-for="tab in tabs"
                :key="toUrl(tab.href)"
                :href="tab.href"
                :aria-current="
                    isCurrentOrParentUrl(tab.href) ? 'page' : undefined
                "
                :class="[
                    '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors',
                    isCurrentOrParentUrl(tab.href)
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground',
                ]"
            >
                <component :is="tab.icon" class="size-4" />
                {{ tab.title }}
            </Link>
        </nav>

        <section class="rounded-xl border bg-card p-5 sm:p-6">
            <div class="max-w-xl space-y-10">
                <slot />
            </div>
        </section>
    </div>
</template>
