<x-filament-panels::page>
    <form wire:submit.prevent="runAction">
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button type="submit">Run</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
