<script setup lang="ts">
import { ref, watch, defineModel, computed } from 'vue';
// import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import { cn } from '@/lib/utils';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';

// Define complex object model
interface StructuredDosage {
  frequency: string;
  full_frequency: string;
  route: string;
  measurement: string;
  special: string[];
  structured: {
    type: string;
    description: string;
  };
  duration: string;
}

const model = defineModel<StructuredDosage>({
  required: true,
  default: () => ({
    frequency: '',
    full_frequency: '',
    route: 'oral',
    measurement: '',
    special: [],
    structured: { type: '', description: '' },
    duration: ''
  })
});


// Frequencies
const frequencies = [
  { code: "QD", full: "once daily", desc: "Take once per day" },
  { code: "BID", full: "twice daily", desc: "Take twice per day" },
  { code: "TID", full: "three times daily", desc: "Take three times per day" },
  { code: "QID", full: "four times daily", desc: "Take four times per day" },
  { code: "Q4H", full: "every 4 hours", desc: "Take every 4 hours" },
  { code: "Q6H", full: "every 6 hours", desc: "Take every 6 hours" },
  { code: "Q8H", full: "every 8 hours", desc: "Take every 8 hours" },
  { code: "Q12H", full: "every 12 hours", desc: "Take every 12 hours" },
  { code: "QOD", full: "every other day", desc: "Take every other day" },
  { code: "PRN", full: "as needed", desc: "Take only when symptoms occur" },
  { code: "HS", full: "at bedtime", desc: "Take before sleeping" },
  { code: "AC", full: "before meals", desc: "Take before meals" },
  { code: "PC", full: "after meals", desc: "Take after meals" },
  { code: "STAT", full: "immediately", desc: "Take immediately" },
];

// Routes of administration
const routes = [
  "oral",
  "topical",
  "sublingual",
  "buccal",
  "inhalation",
  "nasal",
  "ophthalmic",
  "otic",
  "rectal",
  "vaginal",
  "transdermal",
  "intramuscular",
  "intravenous",
  "subcutaneous",
  "injection"
];

// Dosage measurements
const measurements = [
  "½ tablet",
  "1 tablet",
  "2 tablets",
  "3 tablets",
  "4 tablets",
  "1 capsule",
  "2 capsules",
  "5 mL",
  "10 mL",
  "15 mL",
  "1 teaspoon (5 mL)",
  "1 tablespoon (15 mL)",
  "1 application",
  "1 patch",
  "1 puff",
  "2 puffs",
  "1 spray",
  "2 sprays",
  "1 drop",
  "2 drops",
];

// Special instructions
const specialInstructions = [
  "with food",
  "on an empty stomach",
  "with plenty of water",
  "do not crush or chew",
  "crush before taking",
  "shake well before use",
  "after meals",
  "before meals",
  "avoid alcohol",
  "may cause drowsiness",
  "complete full course",
  "store in a cool dry place",
  "keep out of reach of children",
  "refrigerate after opening",
  "take at bedtime",
  "avoid sunlight",

];


// State
const openFreq = ref(false);
const openMeas = ref(false);
const openRoute = ref(false);

// Actions
const selectFrequency = (freq: any) => {
  model.value.frequency = freq.code;
  model.value.full_frequency = freq.full;
  model.value.structured = { type: 'frequency', description: freq.desc };
  openFreq.value = false;
};

const toggleSpecial = (term: string) => {
  const idx = model.value.special.indexOf(term);
  if (idx >= 0) {
    model.value.special.splice(idx, 1);
  } else {
    model.value.special.push(term);
  }
};

// Computed Preview
const previewText = computed(() => {
  const parts = [
    model.value.measurement,
    model.value.route !== 'oral' ? model.value.route : '', // Skip 'oral' often implicit
    model.value.full_frequency,
    model.value.special.join(', '),
    model.value.duration
  ].filter(Boolean);
  return parts.join(' ');
});

</script>

<template>
  <div class="space-y-4 border p-4 rounded-md bg-card">
    <div class="grid grid-cols-2 gap-4">

      <!-- Frequency Selector -->
      <div class="space-y-2">
        <Label>Frequency</Label>
        <Popover v-model:open="openFreq">
          <PopoverTrigger as-child>
            <Button variant="outline" role="combobox" class="w-full justify-between">
              {{ model.frequency ? `${model.frequency} (${model.full_frequency})` : "Select Frequency" }}
              <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
            </Button>
          </PopoverTrigger>
          <PopoverContent class="w-[300px] p-0">
            <Command>
              <CommandInput placeholder="Search frequency..." />
              <CommandList>
                <CommandEmpty>No frequency found.</CommandEmpty>
                <CommandGroup>
                  <CommandItem v-for="f in frequencies" :key="f.code" :value="f.code" @select="selectFrequency(f)">
                    <Check :class="cn('mr-2 h-4 w-4', model.frequency === f.code ? 'opacity-100' : 'opacity-0')" />
                    <div class="flex flex-col">
                      <span>{{ f.code }}</span>
                      <span class="text-xs text-muted-foreground">{{ f.full }}</span>
                    </div>
                  </CommandItem>
                </CommandGroup>
              </CommandList>
            </Command>
          </PopoverContent>
        </Popover>
      </div>

      <!-- Measurement/Amount -->
      <div class="space-y-2">
        <Label>Dosage / Amount</Label>
        <Popover v-model:open="openMeas">
          <PopoverTrigger as-child>
            <Button variant="outline" role="combobox" class="w-full justify-between">
              {{ model.measurement || "Select Amount" }}
              <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
            </Button>
          </PopoverTrigger>
          <PopoverContent class="w-[200px] p-0">
            <Command>
              <CommandInput placeholder="Search amount..." />
              <CommandList>
                <CommandGroup>
                  <CommandItem v-for="m in measurements" :key="m" :value="m"
                    @select="model.measurement = m; openMeas = false">
                    <Check :class="cn('mr-2 h-4 w-4', model.measurement === m ? 'opacity-100' : 'opacity-0')" />
                    {{ m }}
                  </CommandItem>
                </CommandGroup>
              </CommandList>
            </Command>
          </PopoverContent>
        </Popover>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <!-- Route -->
      <div class="space-y-2 w-full">
        <Label>Route</Label>
        <Select v-model="model.route">
          <SelectTrigger class="w-full">
        <SelectValue placeholder="Select Route" />
          </SelectTrigger>
          <SelectContent class="w-full">
        <SelectItem v-for="r in routes" :key="r" :value="r">{{ r }}</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <!-- Duration -->
      <div class="space-y-2">
        <Label>Duration (Optional)</Label>
        <Input v-model="model.duration" placeholder="e.g. for 5 days" />
      </div>
    </div>

    <!-- Special Instructions -->
    <div class="space-y-2 w-[300px]">
      <Label>Special Instructions</Label>
      <Popover v-model:open="openRoute">
      <PopoverTrigger as-child>
        <Button variant="outline" role="combobox" class="w-full justify-between truncate">
        <span class="truncate">
          {{ model.special.length > 0 ? model.special.join(', ') : "Select Special Instructions" }}
        </span>
        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent class="w-[300px] p-0 max-h-60 overflow-auto">
        <Command>
        <CommandInput placeholder="Search special instructions..." />
        <CommandList>
          <CommandGroup>
          <CommandItem v-for="term in specialInstructions" :key="term" :value="term" @select="toggleSpecial(term)">
            <Check :class="cn('mr-2 h-4 w-4', model.special.includes(term) ? 'opacity-100' : 'opacity-0')" />
            {{ term }}
          </CommandItem>
          </CommandGroup>
        </CommandList>
        </Command>
      </PopoverContent>
      </Popover>
    </div>

    <!-- Computed Preview -->
    <div class="mt-4 p-3 bg-muted rounded-md text-sm">
      <span class="font-semibold text-muted-foreground"></span> <span class="text-primary font-medium">{{
        previewText || 'Preview ...' }}</span>
    </div>
  </div>
</template>
