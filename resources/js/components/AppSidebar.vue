<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Archive,
    BookUser,
    Check,
    ChevronsUpDown,
    Clock,
    FileText,
    Inbox,
    LayoutDashboard,
    Loader2,
    Megaphone,
    PenSquare,
    PlugZap,
    ScrollText,
    Send,
    ShieldAlert,
    SlidersHorizontal,
    Star,
    Trash2,
    UserCog,
    Mailbox as MailboxIcon,
    FilePen,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import QuotaMeter from '@/components/mail/QuotaMeter.vue';
import NavUser from '@/components/NavUser.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCompose } from '@/composables/useCompose';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { number } from '@/lib/format';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import broadcasts from '@/routes/broadcasts';
import contacts from '@/routes/contacts';
import mailRoutes from '@/routes/mail';
import templates from '@/routes/templates';

const page = usePage();
const mail = computed(() => page.props.mail);
const isAdmin = computed(() => page.props.auth.user?.is_admin);
const current = computed(
    () =>
        mail.value?.mailboxes.find((m) => m.id === mail.value?.current) ?? null,
);
const { isCurrentUrl, isCurrentOrParentUrl, currentUrl } = useCurrentUrl();
const compose = useCompose();

const folders = computed(() => {
    const id = current.value?.id;

    if (!id) {
        return [];
    }

    const counts = mail.value?.counts;
    const href = (folder: string) =>
        mailRoutes.index.url({ mailbox: id, folder });

    return [
        {
            key: 'inbox',
            title: 'Kotak Masuk',
            icon: Inbox,
            href: href('inbox'),
            badge: counts?.inbox,
            strong: true,
        },
        {
            key: 'starred',
            title: 'Berbintang',
            icon: Star,
            href: href('starred'),
        },
        {
            key: 'scheduled',
            title: 'Antrean & Terjadwal',
            icon: Clock,
            href: href('scheduled'),
            badge: counts?.scheduled,
        },
        { key: 'sent', title: 'Terkirim', icon: Send, href: href('sent') },
        {
            key: 'drafts',
            title: 'Draf',
            icon: FilePen,
            href: href('drafts'),
            badge: counts?.drafts,
        },
        {
            key: 'archive',
            title: 'Arsip',
            icon: Archive,
            href: href('archive'),
        },
        {
            key: 'spam',
            title: 'Spam',
            icon: ShieldAlert,
            href: href('spam'),
            badge: counts?.spam,
        },
        { key: 'trash', title: 'Sampah', icon: Trash2, href: href('trash') },
    ];
});

const isFolderActive = (href: string) =>
    currentUrl.value === href || currentUrl.value.startsWith(`${href}/`);

const work = [
    { title: 'Siaran', icon: Megaphone, href: broadcasts.index.url() },
    { title: 'Templat', icon: FileText, href: templates.index.url() },
    { title: 'Kontak & Grup', icon: BookUser, href: contacts.index.url() },
];

const adminItems = [
    { title: 'Pengguna', icon: UserCog, href: admin.users.index.url() },
    {
        title: 'Domain & Mailbox',
        icon: MailboxIcon,
        href: admin.mailboxes.index.url(),
    },
    { title: 'Integrasi Brevo', icon: PlugZap, href: admin.brevo.url() },
    {
        title: 'Kuota & Pengiriman',
        icon: SlidersHorizontal,
        href: admin.settings.url(),
    },
    { title: 'Log Aktivitas', icon: ScrollText, href: admin.activity.url() },
];

function switchMailbox(id: number) {
    router.visit(mailRoutes.index.url({ mailbox: id, folder: 'inbox' }));
}

function startCompose() {
    if (current.value) {
        compose.start({ mailboxId: current.value.id });
    }
}
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader class="gap-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()"><AppLogo /></Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <SidebarMenu v-if="current">
                <SidebarMenuItem>
                    <DropdownMenu v-if="(mail?.mailboxes.length ?? 0) > 1">
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton
                                class="h-auto border border-sidebar-border bg-sidebar-accent/50 py-2 data-[state=open]:bg-sidebar-accent"
                                :tooltip="current.address"
                            >
                                <MailboxIcon class="text-sidebar-primary" />
                                <span class="grid min-w-0 flex-1 leading-tight">
                                    <span
                                        class="truncate text-[13px] font-medium"
                                        >{{ current.name }}</span
                                    >
                                    <span
                                        class="truncate font-mono text-[11px] text-sidebar-foreground/70"
                                        >{{ current.address }}</span
                                    >
                                </span>
                                <ChevronsUpDown
                                    class="ml-auto size-4 opacity-60"
                                />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            class="w-(--reka-dropdown-menu-trigger-width) min-w-64"
                            align="start"
                        >
                            <DropdownMenuLabel
                                class="text-xs text-muted-foreground"
                                >Ganti mailbox</DropdownMenuLabel
                            >
                            <DropdownMenuItem
                                v-for="m in mail?.mailboxes"
                                :key="m.id"
                                @select="switchMailbox(m.id)"
                            >
                                <span class="grid min-w-0 flex-1 leading-tight">
                                    <span class="truncate text-sm">{{
                                        m.name
                                    }}</span>
                                    <span
                                        class="truncate font-mono text-xs text-muted-foreground"
                                        >{{ m.address }}</span
                                    >
                                </span>
                                <Check
                                    v-if="m.id === current.id"
                                    class="size-4"
                                />
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <SidebarMenuButton
                        v-else
                        as="div"
                        class="h-auto cursor-default border border-sidebar-border bg-sidebar-accent/50 py-2 hover:bg-sidebar-accent/50"
                        :tooltip="current.address"
                    >
                        <MailboxIcon class="text-sidebar-primary" />
                        <span class="grid min-w-0 flex-1 leading-tight">
                            <span class="truncate text-[13px] font-medium">{{
                                current.name
                            }}</span>
                            <span
                                class="truncate font-mono text-[11px] text-sidebar-foreground/70"
                                >{{ current.address }}</span
                            >
                        </span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        class="justify-center bg-sidebar-primary font-semibold text-sidebar-primary-foreground hover:bg-sidebar-primary/90 hover:text-sidebar-primary-foreground active:bg-sidebar-primary/90 active:text-sidebar-primary-foreground"
                        tooltip="Tulis (c)"
                        :disabled="compose.state.loading"
                        @click="startCompose"
                    >
                        <Loader2
                            v-if="compose.state.loading"
                            class="animate-spin"
                        />
                        <PenSquare v-else />
                        <span>Tulis email</span>
                        <kbd
                            class="ml-auto hidden rounded border border-sidebar-primary-foreground/30 px-1.5 font-mono text-[10px] opacity-80 group-data-[collapsible=icon]:hidden lg:inline"
                            >C</kbd
                        >
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup class="py-0">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl(dashboard())"
                            tooltip="Dasbor"
                        >
                            <Link :href="dashboard()"
                                ><LayoutDashboard /><span>Dasbor</span></Link
                            >
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <SidebarGroup v-if="folders.length" class="py-0">
                <SidebarGroupLabel
                    class="text-[11px] font-semibold tracking-wider uppercase"
                    >Kotak surat</SidebarGroupLabel
                >
                <SidebarMenu>
                    <SidebarMenuItem v-for="f in folders" :key="f.key">
                        <SidebarMenuButton
                            as-child
                            :is-active="isFolderActive(f.href)"
                            :tooltip="f.title"
                        >
                            <Link :href="f.href" preserve-state>
                                <component :is="f.icon" />
                                <span
                                    :class="
                                        f.strong && f.badge
                                            ? 'font-semibold'
                                            : ''
                                    "
                                    >{{ f.title }}</span
                                >
                            </Link>
                        </SidebarMenuButton>
                        <SidebarMenuBadge
                            v-if="f.badge"
                            :class="
                                f.strong
                                    ? 'rounded-sm bg-sidebar-primary px-1.5 font-semibold text-sidebar-primary-foreground peer-hover/menu-button:text-sidebar-primary-foreground peer-data-[active=true]/menu-button:text-sidebar-primary-foreground'
                                    : 'text-sidebar-foreground/70'
                            "
                            >{{ number(f.badge) }}</SidebarMenuBadge
                        >
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <SidebarGroup class="py-0">
                <SidebarGroupLabel
                    class="text-[11px] font-semibold tracking-wider uppercase"
                    >Kirim massal & alamat</SidebarGroupLabel
                >
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in work" :key="item.title">
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentOrParentUrl(item.href)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href"
                                ><component :is="item.icon" /><span>{{
                                    item.title
                                }}</span></Link
                            >
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <SidebarGroup v-if="isAdmin" class="py-0">
                <SidebarGroupLabel
                    class="text-[11px] font-semibold tracking-wider uppercase"
                    >Admin</SidebarGroupLabel
                >
                <SidebarMenu>
                    <SidebarMenuItem
                        v-for="item in adminItems"
                        :key="item.title"
                    >
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentOrParentUrl(item.href)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href"
                                ><component :is="item.icon" /><span>{{
                                    item.title
                                }}</span></Link
                            >
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <QuotaMeter />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
