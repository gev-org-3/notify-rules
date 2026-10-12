<x-filament-panels::page>
    @php
        $link = $this->getLink();
        $isConnected = $this->isConnected();
    @endphp

    <div @if ($deepLink) wire:poll.5s="refreshStatus" @endif>
        @if ($deepLink)
            <x-filament::callout
                color="warning"
                icon="heroicon-o-clock"
                :heading="__('notify-rules::notify-rules.telegram.page.pending.heading')"
                :description="__('notify-rules::notify-rules.telegram.page.pending.description', ['minutes' => $this->getLinkTtl()])"
            >
                <x-slot name="footer">
                    <x-filament::button
                        tag="a"
                        :href="$deepLink"
                        target="_blank"
                        rel="noopener noreferrer"
                        icon="heroicon-o-arrow-top-right-on-square"
                    >
                        {{ __('notify-rules::notify-rules.telegram.page.pending.open') }}
                    </x-filament::button>
                </x-slot>
            </x-filament::callout>
        @elseif ($isConnected)
            <x-filament::callout
                color="success"
                icon="heroicon-o-check-circle"
                :heading="filled($link->username)
                    ? __('notify-rules::notify-rules.telegram.page.connected.heading_with_username', ['username' => $link->username])
                    : __('notify-rules::notify-rules.telegram.page.connected.heading')"
                :description="__('notify-rules::notify-rules.telegram.page.connected.description', [
                    'date' => $link->linked_at?->translatedFormat('j F Y, H:i'),
                ])"
            >
                <x-slot name="footer">
                    {{ $this->connectAction }}
                    {{ $this->disconnectAction }}
                </x-slot>
            </x-filament::callout>
        @else
            <x-filament::callout
                color="gray"
                icon="heroicon-o-link-slash"
                :heading="__('notify-rules::notify-rules.telegram.page.not_connected.heading')"
                :description="__('notify-rules::notify-rules.telegram.page.not_connected.description')"
            >
                <x-slot name="footer">
                    {{ $this->connectAction }}
                </x-slot>
            </x-filament::callout>
        @endif
    </div>
</x-filament-panels::page>
