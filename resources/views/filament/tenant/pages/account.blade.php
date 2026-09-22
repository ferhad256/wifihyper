<x-filament-panels::page>
    <form wire:submit="saveProfile">
        {{ $this->profileForm }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Save profile
            </x-filament::button>
        </div>
    </form>

    <form wire:submit="savePassword">
        {{ $this->passwordForm }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Change password
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
