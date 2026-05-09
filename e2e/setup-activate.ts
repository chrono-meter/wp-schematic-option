import { test as base } from '@playwright/test'
import { extendTestWithFixtures } from '@chrono-meter/wp-playwright-helper/fixtures'
const test = extendTestWithFixtures(base)


test.describe('Activate Plugin', () => {
    test('should activate the plugin successfully', async ({ requestUtils }) => {
        await requestUtils.activatePlugin('wp-schematic-option')
        await requestUtils.request.dispose()
    })
})
