/// <reference types="node" />
import type { FullConfig } from '@playwright/test'
import { defineConfig, devices } from '@playwright/test'
import { createRequire } from 'node:module'
import { execSync } from 'node:child_process'
import { chdir } from 'node:process'
import path from 'node:path'
import { wp, enableDebugLog } from '@chrono-meter/wp-playwright-helper/wpcli'

const require = createRequire(import.meta.url)

/**
 * Fill in environment variables before importing `@wordpress/e2e-test-utils-playwright`.
 * 
 * @link https://github.com/WordPress/gutenberg/blob/trunk/packages/e2e-test-utils-playwright/src/config.ts
 */
// process.env.WP_USERNAME = process.env.WP_USERNAME || 'admin'
// process.env.WP_PASSWORD = process.env.WP_PASSWORD || 'password'
process.env.WP_BASE_URL = process.env.WP_BASE_URL || 'http://localhost/.e2etest/'  // Must be ends with a slash.

process.env.PLAYWRIGHT_HTML_HOST = process.env.PLAYWRIGHT_HTML_HOST || (process.env.REMOTE_CONTAINERS === 'true' ? '0.0.0.0' : 'localhost')


/**
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
    testDir: 'e2e',
    /* Run tests in files in parallel */
    fullyParallel: true,
    /* Fail the build on CI if you accidentally left test.only in the source code. */
    forbidOnly: !!process.env.CI,
    /* Retry on CI only */
    retries: process.env.CI ? 2 : 0,
    /* Opt out of parallel tests on CI. */
    workers: process.env.CI ? 1 : undefined,
    /* Reporter to use. See https://playwright.dev/docs/test-reporters */
    reporter: 'html',

    metadata: {
        globalSetup: {
            adminUser: 'admin',
            adminPassword: 'password',
            // adminEmail: 'admin@wordpress.local',
            // siteTitle: 'WordPress e2e Testing',
            version: 'latest',
            locale: 'ja',

            beforeInstallWordPress: async (_config: FullConfig) => {
                const cwd = process.cwd()
                chdir(import.meta.dirname)
                try {
                    execSync('npm run build:js', { stdio: 'inherit' })
                    execSync('npm run zip:test', { stdio: 'inherit' })

                } finally {
                    chdir(cwd)
                }
            },

            afterInstallWordPress: async (_config: FullConfig, installationParams: { abspath: string }) => {
                await enableDebugLog(installationParams.abspath)

                wp('config', 'set', 'CM_SCHEMATIC_OPTION_TEST', 'true', '--type=constant', '--path=' + installationParams.abspath)
                wp('theme', 'install', 'classic', '--activate', '--path=' + installationParams.abspath)  // Else "default"
                wp('plugin', 'install', path.join(import.meta.dirname, './release/test.zip'), '--path=' + installationParams.abspath)
            },
        },
    },

    /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
    use: {
        locale: 'ja-JP',
        baseURL: process.env.WP_BASE_URL,
        ignoreHTTPSErrors: true,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },

    globalSetup: require.resolve('@chrono-meter/wp-playwright-helper/global-setup'),

    /* Configure projects for major browsers */
    projects: [
        {
            name: 'setup',
            testMatch: /setup-.*\.ts/,
        },

        {
            dependencies: ['setup'],
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
            testMatch: /test-.*\.ts/,
        },

        // {
        //     name: 'firefox',
        //     use: { ...devices['Desktop Firefox'] },
        // },

        // {
        //     name: 'webkit',
        //     use: { ...devices['Desktop Safari'] },
        // },

        /* Test against mobile viewports. */
        // {
        //   name: 'Mobile Chrome',
        //   use: { ...devices['Pixel 5'] },
        // },
        // {
        //   name: 'Mobile Safari',
        //   use: { ...devices['iPhone 12'] },
        // },

        /* Test against branded browsers. */
        // {
        //   name: 'Microsoft Edge',
        //   use: { ...devices['Desktop Edge'], channel: 'msedge' },
        // },
        // {
        //   name: 'Google Chrome',
        //   use: { ...devices['Desktop Chrome'], channel: 'chrome' },
        // },
    ],
})
