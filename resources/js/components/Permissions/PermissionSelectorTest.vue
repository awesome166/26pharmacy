<template>
  <div class="p-4 border rounded">
    <h3 class="font-bold mb-4">Checkbox Test</h3>

    <div class="space-y-2">
      <div class="flex items-center space-x-2">
        <input type="checkbox" id="test1" :checked="testState.includes('read')"
          @change="handleNativeChange('read', $event)" />
        <label for="test1">Native Checkbox (Read)</label>
      </div>

      <div class="flex items-center space-x-2">
        <Checkbox id="test2" :checked="testState.includes('create')"
          @update:checked="handleShadcnChange('create', $event)" />
        <label for="test2">Shadcn Checkbox (Create)</label>
      </div>
    </div>

    <div class="mt-4 p-2 bg-gray-100 rounded">
      <strong>Current State:</strong>
      <pre>{{ JSON.stringify(testState, null, 2) }}</pre>
    </div>

    <div class="mt-4 p-2 bg-blue-100 rounded">
      <strong>Event Log:</strong>
      <div class="text-xs space-y-1">
        <div v-for="(log, i) in eventLog" :key="i">{{ log }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';

const testState = ref([]);
const eventLog = ref([]);

const handleNativeChange = (action, event) => {
  const log = `Native: action=${action}, checked=${event.target.checked}`;
  eventLog.value.push(log);
  console.log(log);

  if (event.target.checked) {
    if (!testState.value.includes(action)) {
      testState.value.push(action);
    }
  } else {
    const idx = testState.value.indexOf(action);
    if (idx > -1) {
      testState.value.splice(idx, 1);
    }
  }
};

const handleShadcnChange = (action, value) => {
  const log = `Shadcn: action=${action}, value=${value}, type=${typeof value}`;
  eventLog.value.push(log);
  console.log(log);

  if (value === true) {
    if (!testState.value.includes(action)) {
      testState.value.push(action);
    }
  } else {
    const idx = testState.value.indexOf(action);
    if (idx > -1) {
      testState.value.splice(idx, 1);
    }
  }
};
</script>
