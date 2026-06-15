import { Page } from '@playwright/test';
import { BasePage } from '@poms/base-pages/BasePage';
import { ArticleCreatePage } from '@poms/content-pages/Article/ArticleCreatePage';
import { ArticleEditPage } from '@poms/content-pages/Article/ArticleEditPage';
import { ModerationSideBar } from '@poms/base-pages/ModerationSideBar';
import { TopicsTreeHelper } from '@helpers/general/TopicsTreeHelper';
import { UserPage } from '@poms/base-pages/UserPage';
import { ContentPage } from '@poms/base-pages/ContentPage';
import { AddContentPage } from '@poms/base-pages/AddContentPage';
import { TestSetUpData, TestData } from '../../test-data/TestDataObject';
import { PreviewPage } from '@poms/base-pages/PreviewPage';
import { CreatePages } from '@poms/base-pages/CreatePages';
import { ArticleNodePage } from '@poms/content-pages/Article/ArticleNodePage';
import { DeletePage } from '@poms/base-pages/DeletePage';

export interface SaveOptions
{
    preview: boolean;
    mandatoryFieldCheck: boolean;
};

export interface EditSaveOptions
{
    preview: boolean;
};

export interface DeleteOptions
{
    delete: boolean;
    cancel: boolean;
};

export class ArticleHelper
{
    // pages
    private userPage: UserPage;
    private basePage: BasePage;
    private contentPage: ContentPage;
    private addContentPage: AddContentPage;
    private articleCreatePage: ArticleCreatePage;
    private articleEditPage: ArticleEditPage;
    private moderationSideBar: ModerationSideBar;
    private topicsHelper: TopicsTreeHelper;
    private previewPage: PreviewPage;
    private createPage: CreatePages;
    private articleNodePage: ArticleNodePage;
    private deletePage: DeletePage;

    // constructor
    constructor(
        private page: Page,
        private testSetUpData: typeof TestSetUpData,
        private testData: typeof TestData
    )
    {
        // imported pages
        this.userPage = new UserPage(page, testSetUpData);
        this.basePage = new BasePage(page, testSetUpData);
        this.contentPage = new ContentPage(page, testSetUpData);
        this.addContentPage = new AddContentPage(page, testSetUpData);
        this.articleCreatePage = new ArticleCreatePage(page, testSetUpData, testData);
        this.articleEditPage = new ArticleEditPage(page, testSetUpData, testData);
        this.moderationSideBar = new ModerationSideBar(page, testSetUpData, testData);
        this.topicsHelper = new TopicsTreeHelper(page, testSetUpData, testData);
        this.previewPage = new PreviewPage(page);
        this.createPage = new CreatePages(page, testSetUpData, testData);
        this.articleNodePage = new ArticleNodePage(page, testSetUpData, testData);
        this.deletePage = new DeletePage(page);
    }

    // navigation method
    async navigateTocreateArticle()
    {
        await this.userPage.loggedInPageURLCheck();
        await this.basePage.clickContentLink();
        await this.contentPage.contentPageURLCheck();
        await this.contentPage.clickAddContentButton();
        await this.addContentPage.addContentPageURLCheck();
        await this.addContentPage.selectContent();
    }

    // create Article method
    async createArticle(options: SaveOptions)
    {
        // navigate to create article
        await this.navigateTocreateArticle();

        // set topics for test using selec topics for site before filling article form
        await this.topicsHelper.selectTopicForSite({ edit: false, triggeralert: false });

        // If we're doing mandatory field check, do not fill form
        if (options?.mandatoryFieldCheck)
        {
            await this.articleCreatePage.mandatoryFieldCheck();
        }

        // complete article form using isolated test data 
        await this.articleCreatePage.fillArticleForm({
            articleTitle: this.testData.Article.title,
            revisionLogMessage: this.testData.Article.revisionlog,
            globalTopicChoice: this.testData.GlobalTopics.employment,
            topics: this.topicsHelper.getTopics(),
            articleSummary: this.testData.Article.summary,
            articleBodyField: this.testData.Article.body,
        });

        // updating global topic set
        this.testSetUpData.globalTopicForTest.globalTopic = this.testData.GlobalTopics.employment;

        //Selecting the save as type
        await this.createPage.chooseSaveAsType();

        // if options.preview is set to true, perform preview article method actions
        if (options.preview)
        {
            await this.createPage.clickPreviewButton();
            await this.previewPage.performURLCheck();
            await this.articleNodePage.verifyArticle({
                topics: this.topicsHelper.getTopics()
            });
            await this.previewPage.clickBackToContentEdittingButton();
            await this.articleCreatePage.returnFromPreviewArticlePageURLCheck();
        }

        // Save and verify
        await this.createPage.clickSaveButton();
        await this.articleNodePage.articleNodeURLCheck();
        await this.articleNodePage.verifyArticle({
                topics: this.topicsHelper.getTopics()
            });
    }

    // edit Article method
    async editArticle(options: EditSaveOptions)
    {
        // should be on node page already
        await this.articleNodePage.articleNodeURLCheck();

        // open moderation sidebar
        await this.moderationSideBar.openModerationSideBar();
        // click edit content
        await this.moderationSideBar.clickEditContentButton();

        // set topics for test using selec topics for site before filling article form
        await this.topicsHelper.selectTopicForSite({ edit: true, triggeralert: false });

        // complete article form using edit isolated test data 
        await this.articleEditPage.editArticleForm({
            articleTitle: this.testData.Article.titleEdited,
            revisionLogMessage: this.testData.Article.revisionlogEdited,
            globalTopicChoice: this.testData.GlobalTopics.energy,
            topics: this.topicsHelper.getTopics(),
            articleSummary: this.testData.Article.summaryEdited,
            articleBodyField: this.testData.Article.bodyEdited,
        });

        // setting test set up data to new title
        this.testSetUpData.contentTitleforTest.contentTitle = this.testData.Article.titleEdited;
        this.testSetUpData.globalTopicForTest.globalTopic = this.testData.GlobalTopics.energy;

        //Selecting the save as type
        await this.createPage.chooseSaveAsType();

        // if options.preview is set to true, perform preview article method actions
        if (options.preview)
        {
            await this.createPage.clickPreviewButton();
            await this.previewPage.performURLCheck();
            await this.articleNodePage.verifyEditedArticle({
                topics: this.topicsHelper.getTopics()
            });
            await this.previewPage.clickBackToContentEdittingButton();
            await this.articleEditPage.returnFromPreviewArticlePageURLCheck();
        }

        // Save and verify
        await this.createPage.clickSaveButton();
        await this.articleNodePage.articleNodeURLCheck();
        await this.articleNodePage.verifyEditedArticle({
                topics: this.topicsHelper.getTopics()
            });
    }

    async deleteArticle(options: DeleteOptions)
    {
        // should be on node page already
        await this.articleNodePage.articleNodeURLCheck();
        // open moderation sidebar
        await this.moderationSideBar.openModerationSideBar();
        // click edit content
        await this.moderationSideBar.clickDeleteButton();

        if (options.delete === true)
        {
            await this.deletePage.clickDelete();
            await this.deletePage.deleteNodeCofirmationCheck();
            await this.contentPage.confirmContentDoesNotExist(this.testSetUpData.contentTitleforTest.contentTitle);
            // test moderation state updated to deleted 
            this.testSetUpData.moderationStateForTest.moderationState = this.testSetUpData.validModerationStates.deleted;
        }
        else if (options.cancel === true)
        {
            await this.deletePage.clickCancel();
            await this.articleNodePage.articleNodeURLCheck();
        }
    }

    // create Article method with CK Editor 
    async createArticleWithCKEditorFunctionality(options: SaveOptions)
    {
        // navigate to create article
        await this.navigateTocreateArticle();

        // set topics for test using selec topics for site before filling article form
        await this.topicsHelper.selectTopicForSite({ edit: false, triggeralert: false });


        // complete article form using isolated test data 
        await this.articleCreatePage.fillArticleFormWithCKEditorFunctionality({
            articleTitle: this.testData.Article.title,
            revisionLogMessage: this.testData.Article.revisionlog,
            globalTopicChoice: this.testData.GlobalTopics.employment,
            topics: this.topicsHelper.getTopics(),
            articleSummary: this.testData.Article.summary,
            articleBodyField: this.testData.Article.body,
        });

        // updating global topic set
        this.testSetUpData.globalTopicForTest.globalTopic = this.testData.GlobalTopics.employment;

        //Selecting the save as type
        await this.createPage.chooseSaveAsType();

        // if options.preview is set to true, perform preview article method actions
        if (options.preview)
        {
            await this.createPage.clickPreviewButton();
            await this.previewPage.performURLCheck();
            await this.articleNodePage.verifyArticleCKEditorFullFunctionality();
            await this.previewPage.clickBackToContentEdittingButton();
            await this.articleCreatePage.returnFromPreviewArticlePageURLCheck();
        }

        // Save and verify
        await this.createPage.clickSaveButton();
        await this.articleNodePage.articleNodeURLCheck();
        await this.articleNodePage.verifyArticleCKEditorFullFunctionality();
    }

    // create Article method with CK Editor 
    async createArticleWithImportedFromWord(options: SaveOptions)
    {
        // navigate to create article
        await this.navigateTocreateArticle();

        // set topics for test using selec topics for site before filling article form
        await this.topicsHelper.selectTopicForSite({ edit: false, triggeralert: false });

        // complete article form using isolated test data 
        await this.articleCreatePage.fillArticleFormWithCKEditorImportingFromWord({
            articleTitle: this.testData.Article.title,
            revisionLogMessage: this.testData.Article.revisionlog,
            globalTopicChoice: this.testData.GlobalTopics.employment,
            topics: this.topicsHelper.getTopics(),
            articleSummary: this.testData.Article.summary,
            articleBodyField: this.testData.Article.body,
        });

        // updating global topic set
        this.testSetUpData.globalTopicForTest.globalTopic = this.testData.GlobalTopics.employment;

        //Selecting the save as type
        await this.createPage.chooseSaveAsType();

        // if options.preview is set to true, perform preview article method actions
        if (options.preview)
        {
            await this.createPage.clickPreviewButton();
            await this.previewPage.performURLCheck();
            await this.articleNodePage.verifyArticleCKEditorImportWord();
            await this.previewPage.clickBackToContentEdittingButton();
            await this.articleCreatePage.returnFromPreviewArticlePageURLCheck();
        }

        // Save and verify
        await this.createPage.clickSaveButton();
        await this.articleNodePage.articleNodeURLCheck();
        await this.articleNodePage.verifyArticleCKEditorImportWord();
    }
}




