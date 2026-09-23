@extends('layouts.app')

@section('title', 'Настройки — Lead Control')

@section('content')
    <div class="mx-auto mb-6 max-w-3xl">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Настройки', 'url' => null],
            ]"
        />
    </div>

    <div class="mx-auto flex max-w-3xl flex-col gap-6">
        @if (session('success'))
            <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
        @endif

        @if ($errors->any())
            <x-ui.alert type="error">
                <ul class="m-0 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        {{-- Тема оформления --}}
        <x-ui.card>
            <h2 class="m-0 mb-1 text-base font-semibold text-foreground">Тема оформления</h2>
            <p class="mb-4 text-sm text-muted-foreground">Сохраняется в профиле и синхронизируется с выбором в шапке.</p>
            <div class="flex flex-wrap gap-2">
                <x-ui.button type="button" variant="outline" id="theme-setting-light" class="gap-2">
                    Светлая
                </x-ui.button>
                <x-ui.button type="button" variant="outline" id="theme-setting-dark" class="gap-2">
                    Тёмная
                </x-ui.button>
            </div>
        </x-ui.card>

        {{-- Информация о пользователе --}}
        <x-ui.card>
            <h2 class="m-0 mb-4 text-base font-semibold text-foreground">{!! icon('user', 'icon-sm') !!} Информация о пользователе</h2>

            <x-ui.form-group label="ID" class="mb-4">
                <div class="rounded-md border border-border bg-muted px-3 py-2 font-mono text-sm text-foreground">
                    {{ auth()->user()->user_id }}
                </div>
            </x-ui.form-group>

            <x-ui.form-group label="ФИО" class="mb-4">
                <div class="rounded-md border border-border bg-muted px-3 py-2 text-sm text-foreground">
                    {{ auth()->user()->user_name }}
                </div>
            </x-ui.form-group>

            <x-ui.form-group label="Email" class="mb-0">
                <div class="rounded-md border border-border bg-muted px-3 py-2 text-sm text-foreground">
                    {{ auth()->user()->email }}
                </div>
            </x-ui.form-group>
        </x-ui.card>

        @if (auth()->user()->requiresTwoFactor())
            <x-ui.card>
                <h2 class="m-0 mb-2 text-base font-semibold text-foreground">Двухфакторная аутентификация</h2>
                <p class="mb-4 text-sm text-muted-foreground">
                    Для вашей роли 2FA обязательна.
                    @if (auth()->user()->hasTwoFactorEnabled())
                        Сейчас: <span class="text-foreground font-medium">включена</span>
                        ({{ auth()->user()->two_factor_confirmed_at?->format('d.m.Y H:i') }}).
                    @else
                        Сейчас: <span class="text-foreground font-medium">не настроена</span>.
                    @endif
                </p>
                <x-ui.button href="{{ route('two-factor.setup') }}" variant="outline">
                    {{ auth()->user()->hasTwoFactorEnabled() ? 'Перепривязать приложение' : 'Настроить 2FA' }}
                </x-ui.button>
            </x-ui.card>
        @endif

        {{-- Изменение пароля --}}
        @if (! auth()->user()->hasAnyRole(['call_center', 'senior_manager', 'branch_head', 'tech_director']))
            <x-ui.card>
                <h2 class="m-0 mb-4 text-base font-semibold text-foreground">{!! icon('lock', 'icon-sm') !!} Изменение пароля</h2>

                <form method="POST" action="{{ route('settings.password.update') }}" id="passwordForm" class="space-y-0">
                    @csrf

                    <x-ui.form-group label="Текущий пароль" name="current_password" required class="mb-4">
                        <div class="relative">
                            <x-ui.input
                                type="password"
                                id="current_password"
                                name="current_password"
                                required
                                autocomplete="current-password"
                                class="pr-11"
                                :error="$errors->has('current_password')"
                            />
                            <button
                                type="button"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                onclick="togglePassword('current_password', this)"
                                aria-label="Показать или скрыть пароль"
                            >
                                <span class="js-pw-icon">{!! icon('eye', 'icon-sm') !!}</span>
                            </button>
                        </div>
                    </x-ui.form-group>

                    <x-ui.form-group label="Новый пароль" name="new_password" required class="mb-4">
                        <div class="relative">
                            <x-ui.input
                                type="password"
                                id="new_password"
                                name="new_password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                class="pr-11"
                                :error="$errors->has('new_password')"
                            />
                            <button
                                type="button"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                onclick="togglePassword('new_password', this)"
                                aria-label="Показать или скрыть пароль"
                            >
                                <span class="js-pw-icon">{!! icon('eye', 'icon-sm') !!}</span>
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Минимум 8 символов</p>
                    </x-ui.form-group>

                    <x-ui.form-group label="Подтверждение нового пароля" name="new_password_confirmation" required class="mb-0">
                        <div class="relative">
                            <x-ui.input
                                type="password"
                                id="new_password_confirmation"
                                name="new_password_confirmation"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                class="pr-11"
                                :error="$errors->has('new_password_confirmation')"
                            />
                            <button
                                type="button"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                onclick="togglePassword('new_password_confirmation', this)"
                                aria-label="Показать или скрыть пароль"
                            >
                                <span class="js-pw-icon">{!! icon('eye', 'icon-sm') !!}</span>
                            </button>
                        </div>
                    </x-ui.form-group>

                    <div class="mt-6 flex flex-wrap justify-end gap-3">
                        <x-ui.button type="submit" class="gap-2">
                            {!! icon('save', 'icon-sm') !!}
                            Сохранить пароль
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @else
            <x-ui.card>
                <p class="m-0 text-sm text-muted-foreground">
                    Смена пароля в настройках для вашей роли недоступна. Обратитесь к региональному или генеральному директору.
                </p>
            </x-ui.card>
        @endif

        @if (auth()->user()->hasRole('developer'))
            <x-ui.card>
                <h2 class="m-0 mb-4 text-base font-semibold text-foreground">Блок Разработчика</h2>
                <x-ui.button href="{{ route('ui-kit') }}" variant="outline" class="gap-2">
                    UI Kit
                </x-ui.button>
            </x-ui.card>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('theme-setting-light')?.addEventListener('click', function () {
                if (typeof window.leadControlSetTheme === 'function') {
                    window.leadControlSetTheme('light');
                }
            });
            document.getElementById('theme-setting-dark')?.addEventListener('click', function () {
                if (typeof window.leadControlSetTheme === 'function') {
                    window.leadControlSetTheme('dark');
                }
            });
        });

        const eyeHtml = {!! json_encode(icon('eye', 'icon-sm')) !!};
        const eyeOffHtml = {!! json_encode(icon('eye_off', 'icon-sm')) !!};

        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const iconSpan = button.querySelector('.js-pw-icon');
            if (!input || !iconSpan) return;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            iconSpan.innerHTML = isPassword ? eyeOffHtml : eyeHtml;
        }

        const passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function (e) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('new_password_confirmation').value;

                document.getElementById('new_password').classList.remove('border-destructive');
                document.getElementById('new_password_confirmation').classList.remove('border-destructive');

                if (newPassword.length < 8) {
                    e.preventDefault();
                    Toast.error('Пароль должен содержать минимум 8 символов');
                    document.getElementById('new_password').classList.add('border-destructive');
                    return false;
                }

                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    Toast.error('Пароли не совпадают');
                    document.getElementById('new_password_confirmation').classList.add('border-destructive');
                    return false;
                }
            });
        }
    </script>
@endpush
