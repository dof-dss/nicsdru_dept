import { Page, Locator, expect } from '@playwright/test';
import { TestSteps } from '@poms/base-pages/TestSteps';
import { TestSetUpData } from '../../test-data/TestDataObject';

export class HomePage
{
    // logging
    private readonly testSteps: TestSteps;

    // locators 
    private readonly siteSearchField: Locator;
    private readonly siteSearchButton: Locator;
    private readonly conultationNavBarLink: Locator;
    private readonly moreNewsLink: Locator;
    private readonly publicationNavBarLink: Locator;

    // constructor
    constructor(
        private readonly page: Page,
        private testSetUpData: typeof TestSetUpData
    )
    {
        // logging isolated instance
        this.testSteps = new TestSteps();

        // locators 
        this.siteSearchField = page.locator('#edit-query');
        this.siteSearchButton = page.locator('#edit-submit-search');
        this.conultationNavBarLink = this.page.getByRole('link', { name: 'Consultations', exact: true })
        this.moreNewsLink = this.page.getByRole('link', { name: 'More news...', exact: true })
        this.publicationNavBarLink = this.page.getByRole('link', { name: 'Publications', exact: true })
    }

    // url check using isolated test data for current site being tested
    async homePageURLCheck()
    {
        await this.testSteps.LogInfo(`Verifying URL is "${this.testSetUpData.urlForTest.url}" and user is on home page`);
        await expect(this.page).toHaveURL(this.testSetUpData.urlForTest.url);
    }

    // enter content title in site search field 
    async enterContentTitleInSearch(searchTerm: string)
    {
        await this.testSteps.LogInfo(`Entering "${searchTerm}" imto home page site search bar`);

        await expect(this.siteSearchField).toBeEnabled();
        await this.siteSearchField.fill(searchTerm)
    }

    // click site search button 
    async clickSearchButton()
    {
        await this.testSteps.LogInfo('Clicking site search button');
        await this.siteSearchButton.click();
    }

    // click consultation nav bar link
    async clickConsultationNavLink()
    {
        await this.testSteps.LogInfo('Clicking Consultation nav bar link');
        await this.conultationNavBarLink.click();
    }

    // click more news link 
    async clickMoreNewsLink()
    {
        await this.testSteps.LogInfo('Clicking More news... link');
        await this.moreNewsLink.click();
    }

    // click Publication nav bar link
    async clickPublicationNavLink()
    {
        await this.testSteps.LogInfo('Clicking Publication nav bar link');
        await this.publicationNavBarLink.click();
    }

}