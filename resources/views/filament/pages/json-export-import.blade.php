<x-filament-panels::page>
    <div class="mb-4">
        <x-filament::button wire:click="exportData">Export Posts as JSON</x-filament::button>
    </div>
    <form wire:submit.prevent="importData">
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button type="submit">Import JSON</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
