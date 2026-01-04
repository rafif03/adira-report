<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>{{ __('Delete account') }}</flux:heading>
        <flux:subheading>{{ __('Delete your account and all of its resources') }}</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
            {{ __('Delete account') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form method="POST" wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

                @if($willForceDelete)
                    <flux:subheading>
                        Akun Anda akan <strong>dihapus permanen</strong> karena Anda belum pernah menginput laporan harian.
                        Target bulanan yang terkait dengan akun ini juga akan ikut terhapus.
                    </flux:subheading>

                    <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                        Setelah dihapus permanen, Anda bisa mendaftar lagi menggunakan email yang sama.
                    </p>
                @else
                    <flux:subheading>
                        Akun Anda akan <strong>dinonaktifkan (soft delete)</strong> karena Anda sudah pernah menginput laporan harian.
                        Riwayat laporan akan tetap tersimpan.
                    </flux:subheading>

                    <p class="mt-3 text-sm text-red-600">
                        Email Anda tidak bisa digunakan lagi untuk mendaftar ulang.
                    </p>
                @endif
            </div>

            <flux:input wire:model="password" :label="__('Password')" type="password" />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit">{{ __('Delete account') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
