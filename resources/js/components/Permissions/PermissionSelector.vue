<script setup>
import { ref, computed, watch } from 'vue';

// ─── Props / Emits ────────────────────────────────────────────────────────────
const props = defineProps({
  /** Array of { id, access } — the role's currently assigned permissions */
  modelValue: {
    type: Array,
    default: () => [],
  },
  /** Full permissions list from GET /app/permissions */
  allPermissions: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['update:modelValue']);

// ─── Internal state ───────────────────────────────────────────────────────────
// Map: { [permissionId]: string[] }  e.g. { "abc": ["on"] } or { "xyz": ["read","create"] }
const selected = ref({});
let suppressWatch = false;

// Active group tab
const activeGroup = ref(null);

// ─── Sync from parent (on mount & when role changes) ─────────────────────────
watch(
  () => props.modelValue,
  (newVal) => {
    if (suppressWatch) { suppressWatch = false; return; }

    const map = {};
    (newVal ?? []).forEach((p) => {
      if (!p?.id) return;
      let access = [];
      if (Array.isArray(p.access)) {
        access = [...p.access];
      } else if (typeof p.access === 'string') {
        try { access = JSON.parse(p.access) ?? []; } catch { access = []; }
      }
      if (access.length) map[String(p.id)] = access;
    });
    selected.value = map;
  },
  { immediate: true, deep: true },
);

// ─── Grouped permissions ──────────────────────────────────────────────────────
const groups = computed(() => {
  const g = {};
  props.allPermissions.forEach((p) => {
    const key = p.name.split('.')[0];
    const label = key.charAt(0).toUpperCase() + key.slice(1);
    if (!g[label]) g[label] = [];
    g[label].push(p);
  });
  return g;
});

const groupNames = computed(() => Object.keys(groups.value));

// Auto-select first group
watch(groupNames, (names) => {
  if (names.length && !activeGroup.value) activeGroup.value = names[0];
}, { immediate: true });

const currentPerms = computed(() => groups.value[activeGroup.value] ?? []);

// ─── Helpers ──────────────────────────────────────────────────────────────────
const isOn = (permId) => !!selected.value[String(permId)]?.includes('on');
const hasAction = (permId, action) => !!selected.value[String(permId)]?.includes(action);

/** How many permissions in a group are currently enabled */
const groupActiveCount = (groupLabel) => {
  return (groups.value[groupLabel] ?? []).filter((p) => {
    const acc = selected.value[String(p.id)];
    return acc && acc.length > 0;
  }).length;
};

// ─── Emit ─────────────────────────────────────────────────────────────────────
const emitChange = () => {
  const payload = Object.entries(selected.value)
    .filter(([, acc]) => acc?.length > 0)
    .map(([id, acc]) => ({ id, access: [...acc] }));
  suppressWatch = true;
  emit('update:modelValue', payload);
};

// ─── Toggle on-off ────────────────────────────────────────────────────────────
const toggleOnOff = (permId) => {
  const key = String(permId);
  const cur = selected.value[key];
  if (cur?.includes('on')) {
    // turn off
    const next = { ...selected.value };
    delete next[key];
    selected.value = next;
  } else {
    selected.value = { ...selected.value, [key]: ['on'] };
  }
  emitChange();
};

// ─── Toggle CRUD action ───────────────────────────────────────────────────────
const CRUD_ACTIONS = ['create', 'read', 'update', 'delete'];

const toggleAction = (permId, action) => {
  const key = String(permId);
  const cur = [...(selected.value[key] ?? [])];
  const idx = cur.indexOf(action);

  if (idx > -1) {
    // Remove action
    cur.splice(idx, 1);
    // If removing read, clear all CRUD
    if (action === 'read') {
      const next = { ...selected.value };
      if (cur.length === 0) delete next[key];
      else next[key] = cur.filter((a) => !CRUD_ACTIONS.includes(a));
      selected.value = next;
    } else {
      if (cur.length === 0) {
        const next = { ...selected.value };
        delete next[key];
        selected.value = next;
      } else {
        selected.value = { ...selected.value, [key]: cur };
      }
    }
  } else {
    // Add action
    const next = [...cur, action];
    // Always include read when adding any CRUD sub-action
    if (action !== 'read' && !next.includes('read')) next.push('read');
    selected.value = { ...selected.value, [key]: next };
  }

  emitChange();
};

/** Toggle all CRUD at once (header checkbox) */
const toggleAllCrud = (permId) => {
  const key = String(permId);
  const cur = selected.value[key] ?? [];
  const allChecked = CRUD_ACTIONS.every((a) => cur.includes(a));
  if (allChecked) {
    const next = { ...selected.value };
    delete next[key];
    selected.value = next;
  } else {
    selected.value = { ...selected.value, [key]: [...CRUD_ACTIONS] };
  }
  emitChange();
};

const isCrudAllChecked = (permId) => {
  const acc = selected.value[String(permId)] ?? [];
  return CRUD_ACTIONS.every((a) => acc.includes(a));
};
const isCrudIndeterminate = (permId) => {
  const acc = selected.value[String(permId)] ?? [];
  const count = CRUD_ACTIONS.filter((a) => acc.includes(a)).length;
  return count > 0 && count < CRUD_ACTIONS.length;
};
</script>

<template>
  <div class="ps-root">
    <!-- ── Left: Group Tabs ─────────────────────────────────────────── -->
    <nav class="ps-nav">
      <button v-for="groupName in groupNames" :key="groupName" class="ps-nav-item"
        :class="{ 'ps-nav-item--active': activeGroup === groupName }" @click="activeGroup = groupName">
        <span class="ps-nav-label">{{ groupName }}</span>
        <span class="ps-nav-badge" :class="groupActiveCount(groupName) > 0 ? 'ps-nav-badge--active' : ''">
          {{ groupActiveCount(groupName) }}/{{ (groups[groupName] ?? []).length }}
        </span>
      </button>
    </nav>

    <!-- ── Right: Permission List ───────────────────────────────────── -->
    <div class="ps-panel">
      <!-- Panel Header -->
      <div class="ps-panel-header">
        <span class="ps-panel-title">{{ activeGroup }}</span>
        <span class="ps-panel-count">{{ currentPerms.length }} permission{{ currentPerms.length !== 1 ? 's' : ''
          }}</span>
      </div>

      <!-- Permission Rows -->
      <div class="ps-list">
        <div v-for="perm in currentPerms" :key="perm.id" class="ps-row" :class="{
          'ps-row--on': perm.type === 'on-off' && isOn(perm.id),
          'ps-row--partial': perm.type === 'crud' && isCrudIndeterminate(perm.id),
          'ps-row--full': perm.type === 'crud' && isCrudAllChecked(perm.id),
        }">
          <!-- Left: name + description -->
          <div class="ps-row-info">
            <div class="ps-row-name">
              {{ perm.name.split('.').slice(1).join('_').replace(/_/g, ' ') || perm.name }}
            </div>
            <div class="ps-row-desc">{{ perm.description }}</div>
          </div>

          <!-- Right: controls -->
          <div class="ps-row-controls">
            <!-- ON-OFF → Toggle Switch -->
            <template v-if="perm.type === 'on-off'">
              <button class="ps-toggle" :class="{ 'ps-toggle--on': isOn(perm.id) }" :aria-checked="isOn(perm.id)"
                role="switch" @click="toggleOnOff(perm.id)">
                <span class="ps-toggle-thumb" />
                <span class="sr-only">{{ isOn(perm.id) ? 'Enabled' : 'Disabled' }}</span>
              </button>
            </template>

            <!-- CRUD → Checkboxes -->
            <template v-else-if="perm.type === 'crud'">
              <div class="ps-crud">
                <label v-for="action in ['create', 'read', 'update', 'delete']" :key="action" class="ps-crud-item"
                  :class="{ 'ps-crud-item--checked': hasAction(perm.id, action) }">
                  <input type="checkbox" class="sr-only" :checked="hasAction(perm.id, action)"
                    @change="toggleAction(perm.id, action)" />
                  <span class="ps-crud-box">
                    <svg v-if="hasAction(perm.id, action)" viewBox="0 0 12 12" fill="none">
                      <path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                        stroke-linejoin="round" />
                    </svg>
                  </span>
                  <span class="ps-crud-label">{{ action.charAt(0).toUpperCase() + action.slice(1) }}</span>
                </label>
              </div>
            </template>
          </div>
        </div>

        <div v-if="currentPerms.length === 0" class="ps-empty">
          No permissions in this group.
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ── Root Layout ─────────────────────────────────────────────────────── */
.ps-root {
  display: flex;
  height: 360px;
  /* fixed height — component is self-contained */
  border: 1px solid hsl(var(--border));
  border-radius: 0.625rem;
  overflow: hidden;
  /* clip children, not modal */
  background: hsl(var(--background));
}

/* ── Nav (Left) ──────────────────────────────────────────────────────── */
.ps-nav {
  width: 150px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
  padding: 0.375rem 0.375rem;
  background: hsl(var(--muted) / 0.3);
  border-right: 1px solid hsl(var(--border));
  overflow-y: auto;
  /* nav scrolls if many groups */
}

.ps-nav-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.5rem 0.625rem;
  border-radius: 0.5rem;
  border: none;
  background: transparent;
  cursor: pointer;
  text-align: left;
  transition: background 0.15s, color 0.15s;
  color: hsl(var(--muted-foreground));
  font-size: 0.8125rem;
  font-weight: 500;
}

.ps-nav-item:hover {
  background: hsl(var(--accent));
  color: hsl(var(--accent-foreground));
}

.ps-nav-item--active {
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
}

.ps-nav-label {
  flex: 1;
  min-width: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ps-nav-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 0.1rem 0.35rem;
  border-radius: 999px;
  background: hsl(var(--muted));
  color: hsl(var(--muted-foreground));
  white-space: nowrap;
  transition: background 0.15s, color 0.15s;
}

.ps-nav-item--active .ps-nav-badge {
  background: hsl(var(--primary-foreground) / 0.2);
  color: hsl(var(--primary-foreground));
}

.ps-nav-badge--active {
  background: hsl(142 71% 45% / 0.2);
  color: hsl(142 71% 35%);
}

.ps-nav-item--active .ps-nav-badge--active {
  background: hsl(var(--primary-foreground) / 0.25);
  color: hsl(var(--primary-foreground));
}

/* ── Panel (Right) ───────────────────────────────────────────────────── */
.ps-panel {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  /* panel clips its own list */
}

.ps-panel-header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  padding: 0.625rem 0.875rem;
  border-bottom: 1px solid hsl(var(--border));
  flex-shrink: 0;
}

.ps-panel-title {
  font-size: 0.9375rem;
  font-weight: 700;
  color: hsl(var(--foreground));
}

.ps-panel-count {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
}

/* ── Permission List ─────────────────────────────────────────────────── */
.ps-list {
  flex: 1;
  overflow-y: auto;
  /* list scrolls inside the fixed panel height */
  padding: 0;
}

.ps-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0.875rem;
  border-bottom: 1px solid hsl(var(--border) / 0.4);
  border-left: 3px solid transparent;
  /* reserved for active indicator */
  transition: background 0.15s, border-color 0.15s;
  min-height: 48px;
}

.ps-row:last-child {
  border-bottom: none;
}

.ps-row:hover {
  background: hsl(var(--muted) / 0.35);
}

/* on-off: enabled */
.ps-row--on {
  background: hsl(142 71% 45% / 0.09);
  border-left-color: hsl(142 71% 42%);
}

.ps-row--on:hover {
  background: hsl(142 71% 45% / 0.14);
}

.ps-row--on .ps-row-name {
  color: hsl(142 60% 30%);
}

/* crud: some actions selected */
.ps-row--partial {
  background: hsl(var(--primary) / 0.07);
  border-left-color: hsl(var(--primary) / 0.5);
}

.ps-row--partial:hover {
  background: hsl(var(--primary) / 0.11);
}

.ps-row--partial .ps-row-name {
  color: hsl(var(--primary));
}

/* crud: all actions selected */
.ps-row--full {
  background: hsl(142 71% 45% / 0.09);
  border-left-color: hsl(142 71% 42%);
}

.ps-row--full:hover {
  background: hsl(142 71% 45% / 0.14);
}

.ps-row--full .ps-row-name {
  color: hsl(142 60% 30%);
}

/* ── Row: Info ───────────────────────────────────────────────────────── */
.ps-row-info {
  flex: 1;
  min-width: 0;
}

.ps-row-name {
  font-size: 0.825rem;
  font-weight: 600;
  color: hsl(var(--foreground));
  text-transform: capitalize;
  letter-spacing: 0.01em;
}

.ps-row-desc {
  font-size: 0.725rem;
  color: hsl(var(--muted-foreground));
  margin-top: 0.1rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 260px;
}

/* ── Row: Controls ───────────────────────────────────────────────────── */
.ps-row-controls {
  flex-shrink: 0;
  display: flex;
  align-items: center;
}

/* ── Toggle Switch ───────────────────────────────────────────────────── */
.ps-toggle {
  position: relative;
  display: inline-flex;
  align-items: center;
  width: 40px;
  height: 22px;
  border-radius: 999px;
  /* inactive: slate-blue so it reads as "off but clickable" */
  border: 2px solid hsl(215 20% 60%);
  background: hsl(215 20% 70%);
  cursor: pointer;
  padding: 0;
  transition: background 0.22s, border-color 0.22s, box-shadow 0.22s;
  outline: none;
}

.ps-toggle:hover {
  background: hsl(215 20% 63%);
  border-color: hsl(215 20% 55%);
}

.ps-toggle:focus-visible {
  box-shadow: 0 0 0 2px hsl(var(--ring));
}

/* active/on state: green */
.ps-toggle--on {
  background: hsl(142 60% 42%);
  border-color: hsl(142 60% 36%);
}

.ps-toggle--on:hover {
  background: hsl(142 60% 38%);
}

.ps-toggle-thumb {
  position: absolute;
  left: 2px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: white;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
  transition: transform 0.22s cubic-bezier(.4, 0, .2, 1);
}

.ps-toggle--on .ps-toggle-thumb {
  transform: translateX(18px);
}

/* ── CRUD Checkboxes ─────────────────────────────────────────────────── */
.ps-crud {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.ps-crud-item {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  cursor: pointer;
  padding: 0.25rem 0.4rem;
  border-radius: 0.375rem;
  border: 1px solid hsl(var(--border));
  background: hsl(var(--background));
  transition: background 0.12s, border-color 0.12s;
  user-select: none;
}

.ps-crud-item:hover {
  background: hsl(var(--accent));
}

.ps-crud-item--checked {
  border-color: hsl(var(--primary));
  background: hsl(var(--primary) / 0.08);
}

.ps-crud-box {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 13px;
  height: 13px;
  border-radius: 3px;
  border: 1.5px solid hsl(var(--border));
  background: hsl(var(--background));
  color: hsl(var(--primary));
  transition: border-color 0.12s, background 0.12s;
  flex-shrink: 0;
}

.ps-crud-item--checked .ps-crud-box {
  border-color: hsl(var(--primary));
  background: hsl(var(--primary));
  color: hsl(var(--primary-foreground));
}

.ps-crud-box svg {
  width: 9px;
  height: 9px;
}

.ps-crud-label {
  font-size: 0.6875rem;
  font-weight: 500;
  color: hsl(var(--muted-foreground));
  white-space: nowrap;
}

.ps-crud-item--checked .ps-crud-label {
  color: hsl(var(--foreground));
}

/* ── Empty state ─────────────────────────────────────────────────────── */
.ps-empty {
  padding: 2rem 1rem;
  text-align: center;
  color: hsl(var(--muted-foreground));
  font-size: 0.8125rem;
}

/* ── Screen-reader only ──────────────────────────────────────────────── */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
