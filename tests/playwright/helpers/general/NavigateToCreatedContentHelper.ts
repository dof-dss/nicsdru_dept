import { Page } from '@playwright/test';
import { BasePage } from '@poms/base-pages/BasePage';
import { UserPage } from '@poms/base-pages/UserPage';
import { ContentPage } from '@poms/base-pages/ContentPage';
import { TestSetUpData, TestData } from '../../test-data/TestDataObject';

export interface ContentState {
    active: boolean;
    deleted: boolean;
};

export class NavigateToCreatedContentHelper
{
    // pages
    private readonly userPage: UserPage;
    private readonly basePage: BasePage;
    private readonly contentPage: ContentPage;

    // constructor
    constructor(
        private readonly page: Page,
        private readonly testSetUpData: typeof TestSetUpData,
        private readonly testData: typeof TestData
    )
    {
        // imported pages
        this.userPage = new UserPage(page, testSetUpData);
        this.basePage = new BasePage(page, testSetUpData);
        this.contentPage = new ContentPage(page, testSetUpData);
    }

    // navigation method
    async navigateToCreatedContent(options: ContentState)
    {
        if (options.active)
        {
            await this.basePage.clickContentLink();
            await this.contentPage.contentPageURLCheck();
            await this.contentPage.enterContentNameToReturnTo(this.testSetUpData.contentTitleforTest.contentTitle);
            await this.contentPage.clickFilterButton();
            await this.contentPage.clickTargetContentLink(this.testSetUpData.contentTitleforTest.contentTitle);
        }
        
        if (options.deleted)
        {
            await this.basePage.clickContentLink();
            await this.contentPage.contentPageURLCheck();
            await this.contentPage.enterContentNameToReturnTo(this.testSetUpData.contentTitleforTest.contentTitle);
            await this.contentPage.clickFilterButton();
            await this.contentPage.confirmContentDoesNotExist(this.testSetUpData.contentTitleforTest.contentTitle);
        }


    }

}