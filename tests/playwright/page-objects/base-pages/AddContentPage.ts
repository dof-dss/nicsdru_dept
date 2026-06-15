import { Page, Locator, expect } from '@playwright/test';
import { TestSteps } from '@poms/base-pages/TestSteps';
import { TestSetUpData } from '../../test-data/TestDataObject';

export class AddContentPage
{
    // logging
    private readonly testSteps: TestSteps;

    // constructor
    constructor(
        private readonly page: Page,
        // isolated instances of test data 
        private testSetUpData: typeof TestSetUpData
    ) 
    {
        // logging isolated instance
        this.testSteps = new TestSteps();
    }

    // url check using isolated test data for current site being tested
    async addContentPageURLCheck()
    {
        await this.testSteps.LogInfo(`Performing URL check to ensure user is on "${this.testSetUpData.urlForTest.url}/node/add"`);
        await expect(this.page).toHaveURL(`${this.testSetUpData.urlForTest.url}/node/add`);
    }


    // Clicks the content type link based on isolated test data.
    async selectContent()
    {
        const contentTypeName = this.testSetUpData.contentTypeforTest.contentType;
        await this.testSteps.LogInfo(`Clicking on "${contentTypeName}" link`);
        await this.page.getByRole('link', { name: contentTypeName, exact: true }).click();
    }
}
