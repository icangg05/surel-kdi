<script lang="ts" setup>
import type { ToasterProps } from "vue-sonner"
import { CircleCheckIcon, InfoIcon, Loader2Icon, OctagonXIcon, TriangleAlertIcon, XIcon } from "@lucide/vue"
import { Toaster as Sonner } from "vue-sonner"
import { cn } from "@/lib/utils"

import 'vue-sonner/style.css';

const props = defineProps<ToasterProps>()
</script>

<template>
  <Sonner
    :class="cn('toaster group', props.class)"
    :style="{
      '--normal-bg': 'var(--popover)',
      '--normal-text': 'var(--popover-foreground)',
      '--normal-border': 'var(--border)',
      '--border-radius': 'var(--radius)',
      '--success-bg': 'var(--success-soft)',
      '--success-text': 'var(--success)',
      '--success-border': 'color-mix(in oklch, var(--success) 30%, transparent)',
      '--info-bg': 'var(--info-soft)',
      '--info-text': 'var(--info)',
      '--info-border': 'color-mix(in oklch, var(--info) 30%, transparent)',
      '--warning-bg': 'var(--warning-soft)',
      '--warning-text': 'var(--warning)',
      '--warning-border': 'color-mix(in oklch, var(--warning) 30%, transparent)',
      '--error-bg': 'var(--danger-soft)',
      '--error-text': 'var(--danger)',
      '--error-border': 'color-mix(in oklch, var(--danger) 30%, transparent)',
    }"
    v-bind="props"
  >
    <template #success-icon>
      <CircleCheckIcon class="size-4" />
    </template>
    <template #info-icon>
      <InfoIcon class="size-4" />
    </template>
    <template #warning-icon>
      <TriangleAlertIcon class="size-4" />
    </template>
    <template #error-icon>
      <OctagonXIcon class="size-4" />
    </template>
    <template #loading-icon>
      <div>
        <Loader2Icon class="size-4 animate-spin" />
      </div>
    </template>
    <template #close-icon>
      <XIcon class="size-4" />
    </template>
  </Sonner>
</template>
