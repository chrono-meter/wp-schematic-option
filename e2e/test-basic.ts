import { test as base, expect } from '@playwright/test'
import { extendTestWithFixtures } from '@chrono-meter/wp-playwright-helper/fixtures'
const test = extendTestWithFixtures(base)


test('should activate the plugin successfully', async ({ page, admin }) => {
    await admin.visitAdminPage('options-general.php?page=cm_schematic_option_example')

    const contentToSave = `firstName: John
lastName: Doe
age: 43
`

    const editor = page.getByRole("code").nth(0);
    await editor.click();
    await page.keyboard.press("ControlOrMeta+KeyA");
    await page.keyboard.type(contentToSave);

    // Wait for .dirty to be removed from editor, which indicates that the content has been saved
    await expect(editor).not.toHaveClass(/dirty/);

    await admin.visitAdminPage('options-general.php?page=cm_schematic_option_example_data')

    // #wpbody-content [name="data"] may have json representation of the content
    const textarea = page.locator('#wpbody-content [name="data"]');
    const json = await textarea.inputValue();
    const data = JSON.parse(json);
    expect(data).toEqual({
        firstName: 'John',
        lastName: 'Doe',
        age: 43,
    });

    // #wpbody-content [name="firstName"] should have the value of firstName
    const firstNameInput = page.locator('#wpbody-content [name="firstName"]');
    await expect(firstNameInput).toHaveValue('John');
})
