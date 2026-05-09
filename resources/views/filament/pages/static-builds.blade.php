<x-filament-panels::page>
    <div class="mb-4">
        <x-filament::button wire:click="triggerBuild">
            Trigger Build
        </x-filament::button>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
