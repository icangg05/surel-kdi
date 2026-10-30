<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        confirmLabel?: string;
        destructive?: boolean;
    }>(),
    { confirmLabel: 'Ya, lanjutkan', destructive: false, description: '' },
);

const emit = defineEmits<{ confirm: [] }>();
const open = ref(false);

function confirm() {
    open.value = false;
    emit('confirm');
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ props.title }}</DialogTitle>
                <DialogDescription v-if="props.description">{{
                    props.description
                }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false">Batal</Button>
                <Button
                    :variant="props.destructive ? 'destructive' : 'default'"
                    @click="confirm"
                    >{{ props.confirmLabel }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
