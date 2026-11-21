import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        include: ['tests/js/**/*.test.ts'],
        environment: 'happy-dom',
        globalSetup: ['tests/js/global-setup.ts'],
    },
});
