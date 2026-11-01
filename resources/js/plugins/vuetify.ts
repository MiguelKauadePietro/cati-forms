import '@mdi/font/css/materialdesignicons.css';
import 'vuetify/styles';
import { createVuetify } from 'vuetify';
import { aliases, mdi } from 'vuetify/iconsets/mdi';
import type { Appearance } from '@/types';

// Create inside withApp: SSR requests must never share a theme instance.
export function createAppVuetify(appearance: Appearance) {
    return createVuetify({
        ssr: true,
        defaults: {
            VTextField: { color: 'primary' },
            VCheckbox: { color: 'primary' },
        },
        icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
        theme: {
            defaultTheme: appearance === 'dark' ? 'dark' : 'light',
            themes: {
                light: {
                    dark: false,
                    colors: {
                        primary: '#1976D2',
                        secondary: '#0D47A1',
                        'primary-darken-1': '#0D47A1',
                        'on-secondary': '#FFFFFF',
                        background: '#FFFFFF',
                        surface: '#FFFFFF',
                        'on-primary': '#FFFFFF',
                        'on-background': '#181818',
                        'on-surface': '#181818',
                    },
                },
                dark: {
                    dark: true,
                    colors: {
                        primary: '#1976D2',
                        secondary: '#0D47A1',
                        'primary-darken-1': '#0D47A1',
                        'on-secondary': '#FFFFFF',
                        background: '#0A0A0A',
                        surface: '#121212',
                        'on-primary': '#FFFFFF',
                        'on-background': '#F5F5F5',
                        'on-surface': '#F5F5F5',
                    },
                },
            },
        },
    });
}
