import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import AuthVuetifyLayout from '@/layouts/auth/AuthVuetifyLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { createAppVuetify } from '@/plugins/vuetify';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name === 'auth/Login' || name === 'auth/Register':
                return AuthVuetifyLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app, { page }) => {
        const vuetify = createAppVuetify(page.props.appearance);
        app.use(vuetify);

        let observer: MutationObserver | undefined;
        let stopThemeListener: (() => void) | undefined;
        app.mixin({
            mounted() {
                if (this.$parent) return;

                stopThemeListener = initializeTheme(page.props.appearance);
                const syncTheme = () => {
                    vuetify.theme.change(
                        document.documentElement.classList.contains('dark')
                            ? 'dark'
                            : 'light',
                    );
                };
                // Wait until hydration finishes before resolving system preference.
                syncTheme();
                observer = new MutationObserver(syncTheme);
                observer.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['class'],
                });
            },
            beforeUnmount() {
                if (!this.$parent) {
                    observer?.disconnect();
                    stopThemeListener?.();
                }
            },
        });

        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#1976D2',
    },
});

// This will listen for flash toast data from the server...
initializeFlashToast();
