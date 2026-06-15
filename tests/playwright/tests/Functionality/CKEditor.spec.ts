import { TestSteps } from '@poms/base-pages/TestSteps';
import { test } from '@fixtures/MyFixtures';
import { ModerationSideBar } from '@poms/base-pages/ModerationSideBar';

test.describe('CKEditor Body Field Author Tests', () =>
{
  // Pass the fixture for Article Authors into the beforeEach hook
  test.beforeEach(async ({ loginHelper, testSetUpData, testData }) =>
  {
    const testSteps = new TestSteps();
    await testSteps.LogInfo('Test starting');
    // setting the isolated data for THIS specific test run
    testSetUpData.urlForTest.url = testSetUpData.validTestURLList.finance_url;
    testSetUpData.userForTest.username = testSetUpData.validUserList.author_username;
    testSetUpData.userForTest.password = testSetUpData.validUserList.author_password;
    testSetUpData.contentTypeforTest.contentType = testSetUpData.validContentTypeList.article;
    testSetUpData.contentTitleforTest.contentTitle = testData.Article.title;
    testSetUpData.saveAsOptionForTest.saveAsOption = testSetUpData.validSaveAsOptionList.draft;

    // automatically logins based on above test data for each test
    await loginHelper.loginWithValidUser();
  });

  test('CKEditor-Auth-TC01 - Create - Create Article content as an "Author", perform mandatory field check and preview content', { tag: "@regression" },
    async ({ articleHelper, anonymousHelper, loginHelper, contentModerationHelper, navigateToCreatedContentHelper, testSetUpData }) =>
    {
      await articleHelper.createArticleWithCKEditorFunctionality({
        preview: true,
        mandatoryFieldCheck: false,
      });

      // for anon verification content type needs changed to articleCKEditorFull for search as anon to work correctly
      testSetUpData.contentTypeforTest.contentType = testSetUpData.validContentTypeList.articleCKEditorFull;

      // searching as anon to ensure content is not viewable as draft
      await anonymousHelper.searchAsAnon({ edited: false });

      // log back in and naviagte to content 
      await loginHelper.loginWithValidUser();
      await navigateToCreatedContentHelper.navigateToCreatedContent({
        active: true,
        deleted: false
      });

      // moderating to needs review 
      await contentModerationHelper.authorModerateContent({
        NeedsReview: true,
      });

      // searching as anon to ensure content is not viewable as needs review 
      await anonymousHelper.searchAsAnon({ edited: false });

    });

  test('CKEditor-Auth-TC02 - Create - Create Article content as an "Author", perform mandatory field check and preview content', { tag: "@regression" },
    async ({ articleHelper, anonymousHelper, loginHelper, contentModerationHelper, navigateToCreatedContentHelper, testSetUpData }) =>
    {
      await articleHelper.createArticleWithImportedFromWord({
        preview: true,
        mandatoryFieldCheck: false,
      });

      // for anon verification content type needs changed to articleCKEditorImportWord for search as anon to work correctly
      testSetUpData.contentTypeforTest.contentType = testSetUpData.validContentTypeList.articleCKEditorImportWord;

      // searching as anon to ensure content is not viewable as draft
      await anonymousHelper.searchAsAnon({ edited: false });

      // log back in and naviagte to content 
      await loginHelper.loginWithValidUser();
      await navigateToCreatedContentHelper.navigateToCreatedContent({
        active: true,
        deleted: false
      });

      // moderating to needs review 
      await contentModerationHelper.authorModerateContent({
        NeedsReview: true,
      });

      // searching as anon to ensure content is not viewable as needs review 
      await anonymousHelper.searchAsAnon({ edited: false });

    });
});

test.describe('CKEditor Body Field Article Supervisor Tests', () =>
{
  // Pass the fixture for Article Authors into the beforeEach hook
  test.beforeEach(async ({ loginHelper, testSetUpData, testData }) =>
  {
    const testSteps = new TestSteps();
    await testSteps.LogInfo('Test starting');

    // setting the isolated data for THIS specific test run
    testSetUpData.urlForTest.url = testSetUpData.validTestURLList.finance_url;
    testSetUpData.userForTest.username = testSetUpData.validUserList.supervisor_username;
    testSetUpData.userForTest.password = testSetUpData.validUserList.supervisor_password;
    testSetUpData.contentTypeforTest.contentType = testSetUpData.validContentTypeList.article;
    testSetUpData.contentTitleforTest.contentTitle = testData.Article.title;
    testSetUpData.saveAsOptionForTest.saveAsOption = testSetUpData.validSaveAsOptionList.draft;

    // automatically logins based on above test data for each test
    await loginHelper.loginWithValidUser();
  });

  test('CKEditor-Super-TC01 - Create - Create Article content as an "Supervisor", perform mandatory field check and preview content', { tag: "@regression" },
    async ({ articleHelper, anonymousHelper, loginHelper, contentModerationHelper, navigateToCreatedContentHelper, testSetUpData }) =>
    {
      // creating article as a draft and performing mandatory field check
      console.log(testSetUpData.contentTitleforTest.contentTitle + ' - creating content with this title');

      await articleHelper.createArticleWithCKEditorFunctionality({
        preview: true,
        mandatoryFieldCheck: false,
      });

      // for anon verification content type needs changed to articleCKEditorFull for search as anon to work correctly
      testSetUpData.contentTypeforTest.contentType = testSetUpData.validContentTypeList.articleCKEditorFull;

      // searching as anon to ensure content is not viewable as draft
      await anonymousHelper.searchAsAnon({ edited: false });

      // log back in and naviagte to content 
      await loginHelper.loginWithValidUser();
      await navigateToCreatedContentHelper.navigateToCreatedContent({
        active: true,
        deleted: false
      });

      // moderating to needs review 
      await contentModerationHelper.superVisorModerateContent({
        NeedsReview: true,
        Published: false,
        Archive: false
      });

      // searching as anon to ensure content is not viewable as needs review 
      await anonymousHelper.searchAsAnon({ edited: false });

      // log back in and naviagte to content 
      await loginHelper.loginWithValidUser();
      await navigateToCreatedContentHelper.navigateToCreatedContent({
        active: true,
        deleted: false
      });

      // moderating to published  
      await contentModerationHelper.superVisorModerateContent({
        NeedsReview: false,
        Published: true,
        Archive: false
      });

      // searching as anon to ensure content is viewable as it is published 
      await anonymousHelper.searchAsAnon({ edited: false });

      // log back in and naviagte to content 
      await loginHelper.loginWithValidUser();
      await navigateToCreatedContentHelper.navigateToCreatedContent({
        active: true,
        deleted: false
      });

      // moderating to archived  
      await contentModerationHelper.superVisorModerateContent({
        NeedsReview: false,
        Published: false,
        Archive: true
      });

      // searching as anon to ensure content is viewable as it is published 
      await anonymousHelper.searchAsAnon({ edited: false });

    });

  test('CKEditor-Super-TC02 - Create - Create Article content as an "Supervisor", perform mandatory field check and preview content', { tag: "@regression" },
    async ({ articleHelper, anonymousHelper, loginHelper, contentModerationHelper, navigateToCreatedContentHelper, testSetUpData }) =>
    {
      // creating article as a draft and performing mandatory field check
      console.log(testSetUpData.contentTitleforTest.contentTitle + ' - creating content with this title');

      await articleHelper.createArticleWithImportedFromWord({
        preview: true,
        mandatoryFieldCheck: false,
      });

      // for anon verification content type needs changed to articleCKEditorImportWord for search as anon to work correctly
      testSetUpData.contentTypeforTest.contentType = testSetUpData.validContentTypeList.articleCKEditorImportWord;

      // searching as anon to ensure content is not viewable as draft
      await anonymousHelper.searchAsAnon({ edited: false });

      // log back in and naviagte to content 
      await loginHelper.loginWithValidUser();
      await navigateToCreatedContentHelper.navigateToCreatedContent({
        active: true,
        deleted: false
      });

      // moderating to needs review 
      await contentModerationHelper.superVisorModerateContent({
        NeedsReview: true,
        Published: false,
        Archive: false
      });

      // searching as anon to ensure content is not viewable as needs review 
      await anonymousHelper.searchAsAnon({ edited: false });

      // log back in and naviagte to content 
      await loginHelper.loginWithValidUser();
      await navigateToCreatedContentHelper.navigateToCreatedContent({
        active: true,
        deleted: false
      });

      // moderating to published  
      await contentModerationHelper.superVisorModerateContent({
        NeedsReview: false,
        Published: true,
        Archive: false
      });

      // searching as anon to ensure content is viewable as it is published 
      await anonymousHelper.searchAsAnon({ edited: false });

      // log back in and naviagte to content 
      await loginHelper.loginWithValidUser();
      await navigateToCreatedContentHelper.navigateToCreatedContent({
        active: true,
        deleted: false
      });

      // moderating to archived  
      await contentModerationHelper.superVisorModerateContent({
        NeedsReview: false,
        Published: false,
        Archive: true
      });

      // searching as anon to ensure content is viewable as it is published 
      await anonymousHelper.searchAsAnon({ edited: false });

    });
});
