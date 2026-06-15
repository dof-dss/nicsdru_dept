import { Page } from '@playwright/test';
import { TestSetUpData, TestData } from '../../test-data/TestDataObject';
import { ModerationSideBar } from '@poms/base-pages/ModerationSideBar';

export interface AuthorModerationStates
{
    NeedsReview: boolean;
};

export interface SupervisorModerationStates
{
    NeedsReview: boolean;
    Published: boolean;
    Archive: boolean;
};

export interface StatsSupervisorModerationStates
{
    QuickPublish: boolean;
    NeedsReview: boolean;
    Published: boolean;
    Archive: boolean;
};

export interface TopicSupervisorModerationStates
{
    QuickPublish: boolean;
    NeedsReview: boolean;
    Published: boolean;
    Archive: boolean;
};

export class ContentModerationHelper
{
    // pages
    private readonly moderationSideBar: ModerationSideBar;

    constructor(
        private page: Page,
        // isolated instances of test data via constructor
        private testSetUpData: typeof TestSetUpData,
        private testdata: typeof TestData
    ) 
    {
        // imported pages
        this.moderationSideBar = new ModerationSideBar(page, this.testSetUpData, this.testdata);
    }

    // ----------------- Main user moderation flows -----------------

    async authorModerateContent(wantedState: AuthorModerationStates)
    {
        await this.moderationSideBar.nodeURLCheck(this.testSetUpData.contentTypeforTest.contentType);
        await this.moderationSideBar.openModerationSideBar();
        const currentState = await this.moderationSideBar.getCurrentState();

        if (wantedState.NeedsReview)
        {
            await this.moderationSideBar.publishButtonNotVisible();
            await this.moderationSideBar.clickSubmitForReviewButton();
            this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.needsreview;
        }
    }

    async superVisorModerateContent(wantedState: SupervisorModerationStates)
    {
        await this.moderationSideBar.nodeURLCheck(this.testSetUpData.contentTypeforTest.contentType);
        await this.moderationSideBar.openModerationSideBar();
        const currentState = await this.moderationSideBar.getCurrentState();

        if (wantedState.NeedsReview)
        {
            if (currentState === 'Draft')
            {
                await this.moderationSideBar.publishButtonNotVisible();
                await this.moderationSideBar.clickSubmitForReviewButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.needsreview;
            }

            if (currentState === 'Published' || currentState === 'Archived')
            {
                await this.moderationSideBar.clickRestoreToDraftButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.draft;
                await this.moderationSideBar.openModerationSideBar();
                await this.moderationSideBar.clickSubmitForReviewButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.needsreview;
            }
        }

        if (wantedState.Published)
        {
            if (currentState === 'Draft')
            {
                await this.moderationSideBar.publishButtonNotVisible();
                await this.moderationSideBar.clickSubmitForReviewButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.needsreview;
                await this.moderationSideBar.openModerationSideBar();
                await this.moderationSideBar.clickPublishButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.published;
            }

            if (currentState === 'Needs Review')
            {
                await this.moderationSideBar.clickPublishButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.published;
            }

            if (currentState === 'Archived')
            {
                await this.moderationSideBar.publishButtonNotVisible();
                await this.moderationSideBar.clickRestoreToDraftButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.draft;
                await this.moderationSideBar.openModerationSideBar();
                await this.moderationSideBar.publishButtonNotVisible();
                await this.moderationSideBar.clickSubmitForReviewButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.needsreview;
                await this.moderationSideBar.openModerationSideBar();
                await this.moderationSideBar.clickPublishButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.published;
            }
        }

        if (wantedState.Archive)
        {
            if (currentState !== 'Archived')
            {
                await this.moderationSideBar.clickArchiveButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.archived;
            }
        }
    }

    async statsSuperVisorModerateContent(wantedState: StatsSupervisorModerationStates)
    {
        await this.moderationSideBar.nodeURLCheck(this.testSetUpData.contentTypeforTest.contentType);
        await this.moderationSideBar.openModerationSideBar();
        const currentState = await this.moderationSideBar.getCurrentState();

        if (wantedState.QuickPublish)
        {
            if (currentState === 'Draft')
            {
                await this.moderationSideBar.clickQuickPublishButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.published;
            }

            if (currentState === 'Needs Review')
            {
                await this.moderationSideBar.clickPublishButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.published;
            }

            if (currentState === 'Archived')
            {
                await this.moderationSideBar.clickRestoreToDraftButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.draft;
                await this.moderationSideBar.openModerationSideBar();
                await this.moderationSideBar.clickQuickPublishButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.published;
            }
        }

        if (wantedState.NeedsReview)
        {
            if (currentState === 'Draft')
            {
                await this.moderationSideBar.clickSubmitForReviewButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.needsreview;
            }

            if (currentState === 'Published' || currentState === 'Archived')
            {
                await this.moderationSideBar.clickRestoreToDraftButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.draft;
                await this.moderationSideBar.openModerationSideBar();
                await this.moderationSideBar.clickSubmitForReviewButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.needsreview;
            }
        }

        if (wantedState.Published)
        {
            if (currentState === 'Draft')
            {
                await this.moderationSideBar.clickSubmitForReviewButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.needsreview;
                await this.moderationSideBar.openModerationSideBar();
                await this.moderationSideBar.clickPublishButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.published;
            }

            if (currentState === 'Needs Review')
            {
                await this.moderationSideBar.clickPublishButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.published;
            }

            if (currentState === 'Archived')
            {
                await this.moderationSideBar.clickRestoreToDraftButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.draft;
                await this.moderationSideBar.openModerationSideBar();
                await this.moderationSideBar.clickSubmitForReviewButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.needsreview;
                await this.moderationSideBar.openModerationSideBar();
                await this.moderationSideBar.clickPublishButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.published;
            }
        }

        if (wantedState.Archive)
        {
            if (currentState !== 'Archived')
            {
                await this.moderationSideBar.clickArchiveButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.archived;
            }
        }
    }

    async topicSuperVisorModerateContent(wantedState: TopicSupervisorModerationStates)
    {
        await this.moderationSideBar.nodeURLCheck(this.testSetUpData.contentTypeforTest.contentType);
        await this.moderationSideBar.openModerationSideBar();
        const currentState = await this.moderationSideBar.getCurrentState();

        if (wantedState.QuickPublish)
        {
            if (currentState === 'Draft')
            {
                await this.moderationSideBar.clickQuickPublishButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.published;
            }

            if (currentState === 'Needs Review')
            {
                await this.moderationSideBar.clickPublishButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.published;
            }

            if (currentState === 'Archived')
            {
                await this.moderationSideBar.clickRestoreToDraftButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.draft;
                await this.moderationSideBar.openModerationSideBar();
                await this.moderationSideBar.clickQuickPublishButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.published;
            }
        }

        if (wantedState.NeedsReview)
        {
            if (currentState === 'Draft')
            {
                await this.moderationSideBar.clickSubmitForReviewButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.needsreview;
            }

            if (currentState === 'Published' || currentState === 'Archived')
            {
                await this.moderationSideBar.clickRestoreToDraftButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.draft;
                await this.moderationSideBar.openModerationSideBar();
                await this.moderationSideBar.clickSubmitForReviewButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.needsreview;
            }
        }

        if (wantedState.Published)
        {
            if (currentState === 'Draft')
            {
                await this.moderationSideBar.clickSubmitForReviewButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.needsreview;
                await this.moderationSideBar.openModerationSideBar();
                await this.moderationSideBar.clickPublishButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.published;
            }

            if (currentState === 'Needs Review')
            {
                await this.moderationSideBar.clickPublishButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.published;
            }

            if (currentState === 'Archived')
            {
                await this.moderationSideBar.clickRestoreToDraftButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.draft;
                await this.moderationSideBar.openModerationSideBar();
                await this.moderationSideBar.clickSubmitForReviewButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.needsreview;
                await this.moderationSideBar.openModerationSideBar();
                await this.moderationSideBar.clickPublishButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.published;
            }
        }

        if (wantedState.Archive)
        {
            if (currentState !== 'Archived')
            {
                await this.moderationSideBar.clickArchiveButton();
                this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.archived;
            }
        }
    }


}