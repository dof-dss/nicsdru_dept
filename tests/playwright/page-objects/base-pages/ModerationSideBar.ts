import { Page, Locator } from '@playwright/test';
import { TestSteps } from '@poms/base-pages/TestSteps';
import { TestData, TestSetUpData } from '../../test-data/TestDataObject';
import { expect } from '@playwright/test';
import { ContentNodePageRouter } from '@helpers/general/ContentNodePageRouter';

export class ModerationSideBar
{
    // logging
    private readonly testSteps: TestSteps;

    // locators
    private readonly moderationsidebar: Locator;
    private readonly editcontentbutton: Locator;
    private readonly submitforreviewbutton: Locator;
    private readonly rejectbutton: Locator;
    private readonly publishbutton: Locator;
    private readonly quickpublishbutton: Locator;
    private readonly archivebutton: Locator;
    private readonly restorebutton: Locator;
    private readonly restoretodraftbutton: Locator;
    private readonly deletebutton: Locator;
    private readonly showrevisions: Locator;
    private readonly currentstate: Locator;
    private readonly moderationSidebarTrigger: Locator;

    // router
    private readonly contentRouter: ContentNodePageRouter;

    // constructor
    constructor(
        private readonly page: Page,
        private testSetUpData: typeof TestSetUpData,
        private testData: typeof TestData
    )
    {
        // logging isolated instance
        this.testSteps = new TestSteps();
        this.contentRouter = new ContentNodePageRouter(page, this.testSetUpData, this.testData);

        // moderations side bar locators 
        this.moderationsidebar = page.locator('//*[@id="toolbar-bar"]//a[contains(text(),"Tasks")]');
        this.editcontentbutton = page.getByRole('link', { name: 'Edit content' });
        this.submitforreviewbutton = page.locator('//input[@id="submit_for_review"]');
        this.rejectbutton = page.locator('//input[@id="reject"]');
        this.publishbutton = page.getByRole('button', { name: 'Publish' });
        this.quickpublishbutton = page.getByRole('button', { name: 'Quick Publish' });
        this.archivebutton = page.getByRole('button', { name: 'Archive' });
        this.restorebutton = page.getByRole('button', { name: 'Restore' });
        this.restoretodraftbutton = page.getByRole('button', { name: 'Restore to Draft' });
        this.deletebutton = page.getByRole('link', { name: 'Delete content' });
        this.showrevisions = page.locator('//a[contains(text(),"Revisions")]');
        this.currentstate = page.locator('//div//strong');
        this.moderationSidebarTrigger = page.locator('a.use-ajax[data-dialog-renderer="off_canvas"]');
    }
    // ------------ Assert ------------

    async nodeURLCheck(contentType: string): Promise<void>
    {
        const actualType = this.testSetUpData.contentTypeforTest.contentType;
        await this.contentRouter.verifyNodeURL(actualType);
    }

    // ------------ Actions on Moderation side bar ------------

    async openModerationSideBar()
    {
        // make sure moderationsidebar is in the dom and visible
        await this.moderationSidebarTrigger.waitFor({ state: 'visible' });
        await expect(this.moderationSidebarTrigger).toBeEnabled();

        // wait until drupal has attached AJAX behaviour 
        // await this.page.waitForFunction(el => el.classList.contains('ajax-processed'),
        //     await this.moderationSidebarTrigger.elementHandle()
        // );

        await this.page.waitForTimeout(500);

        await this.testSteps.LogInfo('Clicking tasks to open Moderation Sidebar');
        await this.moderationSidebarTrigger.click();

        const sidebar = this.page.locator('.ui-dialog-off-canvas');
        await this.testSteps.LogInfo('Waiting for Moderation sidebar to be open');
        await expect(sidebar).toBeVisible();
        await expect(sidebar).toBeEnabled();
    }

    async clickEditContentButton()
    {
        await this.testSteps.LogInfo('Clicking "Edit" button in Moderation Sidebar');
        await this.editcontentbutton.click();
    }

    async clickSubmitForReviewButton()
    {
        await expect(this.submitforreviewbutton).toBeEnabled();
        await this.testSteps.LogInfo('Clicking "Submit for Review" button in Moderation Sidebar');
        await this.submitforreviewbutton.click();
        await this.verifyContentHasBeenUpdated();
    }

    async clickRejectButton()
    {
        await expect(this.rejectbutton).toBeEnabled();
        await this.testSteps.LogInfo('Clicking "Reject" button in Moderation Sidebar');
        await this.rejectbutton.click();
        await this.verifyContentHasBeenUpdated();
    }

    async clickPublishButton()
    {
        await expect(this.publishbutton).toBeEnabled();
        await this.moderateAlertAccept();
        await this.testSteps.LogInfo('Clicking "Publish" button in Moderation Sidebar');
        await this.publishbutton.click();
        await this.verifyContentHasBeenUpdated();
    }

    async clickQuickPublishButton()
    {
        await expect(this.quickpublishbutton).toBeEnabled();
        await this.moderateAlertAccept();
        await this.testSteps.LogInfo('Clicking "Quick publish" button in Moderation Sidebar');
        await this.quickpublishbutton.click();
        await this.verifyContentHasBeenUpdated();
    }

    async clickArchiveButton()
    {
        await expect(this.archivebutton).toBeEnabled();
        await this.moderateAlertAccept();
        await this.testSteps.LogInfo('Clicking "Archive" button in Moderation Sidebar');
        await this.archivebutton.click();
        await this.verifyContentHasBeenUpdated();
    }

    async clickRestoreButton()
    {
        await expect(this.restorebutton).toBeEnabled();
        await this.testSteps.LogInfo('Clicking "Restore" button in Moderation Sidebar');
        await this.restorebutton.click();
        await this.verifyContentHasBeenUpdated();
    }

    async clickRestoreToDraftButton()
    {
        await expect(this.restoretodraftbutton).toBeEnabled();
        await this.testSteps.LogInfo('Clicking "Restore to draft" button in Moderation Sidebar');
        await this.restoretodraftbutton.click();
        await this.verifyContentHasBeenUpdated();
    }

    async clickDeleteButton()
    {
        await expect(this.deletebutton).toBeEnabled();
        await this.testSteps.LogInfo('Clicking "Delete" button in Moderation Sidebar');
        await this.moderateAlertAccept();
        await this.deletebutton.click();
    }


    async clickShowRevisionsButton()
    {
        await expect(this.showrevisions).toBeEnabled();
        await this.testSteps.LogInfo('Clicking "Revisions" button in Moderation Sidebar');
        await this.showrevisions.click();
    }


    // ------------ Buttons Not Visible ------------
    async deleteButtonNotVisible()
    {
        await this.testSteps.LogInfo('Verifying "Delete" button is NOT visible');
        await expect(this.deletebutton).toBeHidden();
    }

    async publishButtonNotVisible()
    {
        await this.testSteps.LogInfo('Verifying "Publish" button is NOT visible');
        await expect(this.publishbutton).toBeHidden();
    }

    // ------------ Moderation Actions and asserts ------------

    // get moderation state
    async getCurrentState()
    {
        await this.page.locator('//div[@class="moderation-sidebar-info"]//strong').waitFor({ state: 'visible', timeout: 2000 });
        return (await this.page.locator('//div[@class="moderation-sidebar-info"]//strong').textContent());
    }

    // verify update has occured
    async verifyContentHasBeenUpdated()
    {
        await this.testSteps.LogInfo('Verifying Content has been updated message is visible');
        await expect(this.page.locator('.messages--status')).toContainText('has been updated');
    }

    // verify moderation state
    async verifyCurrentState(currentState: string)
    {
        await this.testSteps.LogInfo(`Verifying Current moderation state is "${currentState}"`);
        await expect(this.page.locator('//div[@class="moderation-sidebar-info"]/p/strong')).toHaveText(currentState);
    }

    // Alert listener - called before clicking element that triggers browser alert will accept the alert
    async moderateAlertAccept()
    {
        this.page.once('dialog', async dialog =>
        {
            expect(dialog.type()).toBe('confirm');
            await dialog.accept();
            await this.testSteps.LogInfo('Accept alert to confirm decision');
        });
    }
}