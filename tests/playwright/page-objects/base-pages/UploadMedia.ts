import { Page, Locator, expect } from '@playwright/test';
import { TestSteps } from '@poms/base-pages/TestSteps';
import { TestData, TestSetUpData } from '@tdata/TestDataObject';
import path from 'path';

export interface GalleryImageValues
{
    alt: string;
    title: string;
    caption: string;
    name: string;
};

export class UploadMedia
{
    // logging
    private readonly testSteps: TestSteps;

    // Locators
    // add media attachment button
    private readonly addMediaAttachmentButton: Locator;
    private readonly editPageAddMediaAttachmentButton: Locator;

    // add media publication button
    private readonly addMediaAttachmentPublicationButton: Locator;
    private readonly editPageAddMediaAttachmentPublicationButton: Locator;

    // add media standard button
    private readonly addMediaImageButton: Locator;
    private readonly editPageAddMediaImageButton: Locator;

    // add media gallery button
    private readonly addGalleryMediaImageButton: Locator;
    private readonly editGalleryPageAddMediaImageButton: Locator;

    // add remote video button
    private readonly addMediaRemoteVideoButton: Locator;
    private readonly editPageMediaRemoteVideoButton: Locator;

    private readonly uploadAttachmentField: Locator;
    private readonly uploadImageField: Locator;
    private readonly uploadRemoteVideoField: Locator;

    private readonly mediAltTextField: Locator;
    private readonly mediaTitleField: Locator;
    private readonly mediaCaptionField: Locator;
    private readonly mediaNameField: Locator;

    private readonly addMediaSave: Locator;
    private readonly mediaSearchNameField: Locator;
    private readonly applyFiltersButton: Locator;
    private readonly insertSelectedButton: Locator;

    private readonly uploadAudioField: Locator;

    private readonly importWordFileInputField: Locator;

    // constructor
    constructor
        (
            private readonly page: Page,
            // isolated instances of test data via constructor
            private testSetUpData: typeof TestSetUpData,
            private testdata: typeof TestData
        )
    {
        // logging isolated instance
        this.testSteps = new TestSteps();

        // locators
        this.addMediaAttachmentButton = page.locator('#edit-field-attachment-open-button');
        this.editPageAddMediaAttachmentButton = page.locator('//input[contains(@id, "edit-field-attachment-open-button")]');

        this.addMediaAttachmentPublicationButton = page.locator('#edit-field-publication-files-open-button');
        this.editPageAddMediaAttachmentPublicationButton = page.locator('//input[contains(@id, "edit-field-publication-files-open-button")]');

        this.addMediaImageButton = page.locator('#edit-field-photo-open-button');
        this.editPageAddMediaImageButton = page.locator('//input[contains(@id, "edit-field-photo-open-button")]');

        this.addGalleryMediaImageButton = page.locator('#edit-field-gallery-images-open-button');
        this.editGalleryPageAddMediaImageButton = page.locator('//input[contains(@id, "edit-field-gallery-images-open-button")]');

        this.addMediaRemoteVideoButton = page.locator('#edit-field-video-open-button');
        this.editPageMediaRemoteVideoButton = page.locator('//input[contains(@id, "edit-field-video-open-button")]');

        this.uploadAttachmentField = page.locator('//input[contains(@id,"edit-upload-upload")][1]');
        this.uploadImageField = page.locator('//input[contains(@id,"edit-upload-upload")][1]');
        this.uploadRemoteVideoField = page.locator('//input[contains(@id,"edit-url")]');

        this.mediAltTextField = page.locator('//input[contains(@id,"edit-media-0-fields-field-media-image-0-alt")]');
        this.mediaTitleField = page.locator('//input[contains(@id,"edit-media-0-fields-field-media-image-0-title")]');
        this.mediaCaptionField = page.locator('//input[contains(@id,"edit-media-0-fields-field-caption-0-value")]');
        this.mediaNameField = page.locator('//input[contains(@id,"edit-media-0-fields-name-0-value")]');

        this.addMediaSave = page.locator('//button[text()="Save"]');
        this.mediaSearchNameField = page.locator('//input[contains(@id,"edit-name")]');
        this.applyFiltersButton = page.locator('//input[contains(@id,"edit-submit-media-library")]');
        this.insertSelectedButton = page.locator('//button[text()="Insert selected"]');

        this.uploadAudioField = page.locator('//input[contains(@id,"edit-upload-upload")][1]');

        this.importWordFileInputField = page.locator('//input[@class="ck-hidden"]');
    }

    // ---------------------- BUTTONS ---------------------------

    // click Add media button for attachments
    async clickAddMediaAttachmentButton()
    {
        await this.testSteps.LogInfo('Clicking Add media attachment Button');
        if (this.testSetUpData.contentTypeforTest.contentType === this.testSetUpData.validContentTypeList.publication)
        {
            await this.addMediaAttachmentPublicationButton.click();
        }
        else
        {
            await this.addMediaAttachmentButton.click();
        }
    }

    // click Add media button for attachments
    async clickEditPageAddMediaAttachmentButton()
    {
        await this.testSteps.LogInfo('Clicking Add media attachment Button while editing');
        if (this.testSetUpData.contentTypeforTest.contentType === this.testSetUpData.validContentTypeList.publication)
        {
            await this.editPageAddMediaAttachmentPublicationButton.click();
        }
        else
        {
            await this.editPageAddMediaAttachmentButton.click();
        }
    }

    // click Add media button for images
    async clickAddMediaImageButton()
    {
        await this.testSteps.LogInfo('Clicking Add media image Button');
        if (this.testSetUpData.contentTypeforTest.contentType === this.testSetUpData.validContentTypeList.gallery)
        {
            await this.addGalleryMediaImageButton.click();
        }
        else
        {
            await this.addMediaImageButton.click();
        }
    }

    // click Add media button for attachments on edit page after removal of original pdf
    async clickEditPageAddMediaImageButton()
    {
        await this.testSteps.LogInfo('Clicking Add media image Button while editing');
        if (this.testSetUpData.contentTypeforTest.contentType === this.testSetUpData.validContentTypeList.gallery)
        {
            await this.editGalleryPageAddMediaImageButton.click();
        }
        else
        {
            await this.editPageAddMediaImageButton.click();
        }
    }

    // click Add media button for remote video
    async clickAddMediaRemoteVideoButton()
    {
        await this.testSteps.LogInfo('Clicking Add media remote video Button');
        await this.addMediaRemoteVideoButton.click();
    }

    // click Add media button for remote video
    async clickEditPageAddMediaRemoteVideoButton()
    {
        await this.testSteps.LogInfo('Clicking Add media remote video Button while editing');
        await this.editPageMediaRemoteVideoButton.click();
    }


    // ---------------------- UPLOADS ---------------------------

    // Upload attachment
    async uploadAttachmnetProcess(attachmentFileName: string)
    {
        const filePath = path.join(process.cwd(), 'FileUploadSamples', attachmentFileName);
        await this.testSteps.LogInfo(`Uploading attachment "${attachmentFileName}" from files`);
        await this.uploadAttachmentField.setInputFiles(filePath);
    }

    // Upload image
    async uploadImageProcess(imageFileName: string)
    {
        const filePath = path.join(process.cwd(), 'FileUploadSamples', imageFileName);
        await this.testSteps.LogInfo(`Uploading image "${imageFileName}" from files`);
        await this.uploadAttachmentField.setInputFiles(filePath);
    }

    // Upload mulitple gallery images
    async uploadMultipleGalleryImagesProcess(galleryFileName: string, gallery2FileName: string, gallery3FileName: string, gallery4FileName: string, gallery5FileName: string,)
    {
        const gallery1FilePath = path.join(process.cwd(), 'FileUploadSamples', galleryFileName);
        const gallery2FilePath = path.join(process.cwd(), 'FileUploadSamples', gallery2FileName);
        const gallery3FilePath = path.join(process.cwd(), 'FileUploadSamples', gallery3FileName);
        const gallery4FilePath = path.join(process.cwd(), 'FileUploadSamples', gallery4FileName);
        const gallery5FilePath = path.join(process.cwd(), 'FileUploadSamples', gallery5FileName);

        await this.testSteps.LogInfo(`Uploading gallery images "${galleryFileName}" "${gallery2FileName}" "${gallery3FileName}" "${gallery4FileName}" "${gallery5FileName}" from files`);
        await this.uploadAttachmentField.setInputFiles([
            gallery1FilePath,
            gallery2FilePath,
            gallery3FilePath,
            gallery4FilePath,
            gallery5FilePath
        ]);
    }

    // Upload image
    async uploadAudioFileProcess(audioFileName: string)
    {
        const filePath = path.join(process.cwd(), 'FileUploadSamples', audioFileName);
        await this.testSteps.LogInfo(`Uploading audio file "${audioFileName}" from files`);
        await this.uploadAudioField.setInputFiles(filePath);
    }

    // Upload remote video
    async addRemoteVideoLinkProcess(videoURL: string)
    {
        await this.testSteps.LogInfo(`Entering remote video URL "${videoURL}"`);
        await this.uploadRemoteVideoField.fill(videoURL);
    }

    // Upload image
    async ImportWordFile(WordFile: string)
    {
        const filePath = path.join(process.cwd(), 'FileUploadSamples', WordFile);
        await this.testSteps.LogInfo(`Uploading image "${WordFile}" from files`);
        await this.importWordFileInputField.setInputFiles(filePath);
    }

    // ---------------------- FIELD NAMES ---------------------------

    // Add alt text, title, caption and name 
    async enterImageValues(addMediaAltText: string, addMediaTitle: string, addMediaCaption: string, addMediaName: string)
    {
        // alt text
        await this.testSteps.LogInfo(`Entering media Alt text "${addMediaAltText}"`);
        await this.mediAltTextField.fill(addMediaAltText);

        // title
        await this.testSteps.LogInfo(`Entering media Title "${addMediaTitle}"`);
        await this.mediaTitleField.fill(addMediaTitle);

        // caption
        await this.testSteps.LogInfo(`Entering media Caption "${addMediaCaption}"`);
        await this.mediaCaptionField.fill(addMediaCaption);

        // name
        await this.testSteps.LogInfo(`Entering media name "${addMediaName}"`);
        await this.mediaNameField.fill(addMediaName);
    }

    // fill image details 
    async fillMultipleGalleryImageDetails(details: GalleryImageValues[])
    {
        for (let i = 0; i < details.length; i++)
        {
            const values = details[i];
            await this.page.fill(`//input[contains(@id, "edit-media-${i}-fields-field-media-image-0-alt")]`, values.alt);
            await this.page.fill(`//input[contains(@id, "edit-media-${i}-fields-field-media-image-0-title")]`, values.title);
            await this.page.fill(`//input[contains(@id, "edit-media-${i}-fields-field-caption-0-value")]`, values.caption);
            await this.page.fill(`//input[contains(@id, "edit-media-${i}-fields-name-0-value")]`, values.name);
        }
    }


    // Add just Name ( mainly used in edit tests )
    async enterMediaName(addMediaName: string)
    {

        await this.testSteps.LogInfo('Verifying media name field option is enabled');
        await expect(this.mediaNameField).toBeEnabled();

        await this.testSteps.LogInfo(`Entering media name "${addMediaName}"`);
        await this.mediaNameField.fill(addMediaName);
    }

    // ---------------------- SAVE BUTTON ---------------------------


    // click Media Save
    async clickMediaSave()
    {
        await this.testSteps.LogInfo('Clicking Save');
        await this.addMediaSave.click();
    }

    // ---------------------- MODAL ACTIONS ---------------------------

    // Enter Media Seach name
    async enterMediaSearchTitleField(mediaSearchName: string)
    {
        await this.testSteps.LogInfo(`Entering media name "${mediaSearchName}" in search field`);
        await this.mediaSearchNameField.fill(mediaSearchName);
    }

    // click Apply Filter
    async clickApplyFilters()
    {
        await this.testSteps.LogInfo('Clicking Apply Filter button');
        await this.applyFiltersButton.click();
    }

    // click Insert Selected button
    async clickInsertSelectedButton()
    {
        await this.testSteps.LogInfo('Clicking Insert Selected button');
        await this.insertSelectedButton.click();
    }

    // ---------------------- verification ---------------------------

    //verify Attachment is uploaded 
    async verifyAttachmentUploaded()
    {
        await this.testSteps.LogInfo('Verifying Attachement is uploaded');

        // looking for 'x' button to remove to ensure it has been properly uploaded
        if (this.testSetUpData.contentTypeforTest.contentType === this.testSetUpData.validContentTypeList.publication)
        {
            await expect(this.page.locator('//input[contains(@id,"edit-field-publication-files-selection-0-remove-button")]')).toBeVisible();
            await expect(this.page.locator('//input[contains(@id,"edit-field-publication-files-selection-0-remove-button")]')).toBeEnabled();
        }
        else
        {
            await expect(this.page.locator('//input[contains(@id,"edit-field-attachment-selection-0-remove-button")]')).toBeVisible();
            await expect(this.page.locator('//input[contains(@id,"edit-field-attachment-selection-0-remove-button")]')).toBeEnabled();
        }
    }

    //verify Image is uploaded 
    async verifyImageUploaded()
    {
        await this.testSteps.LogInfo('Verifying Image is uploaded');

        // looing for 'x' button to remove to ensure it has been properly uploaded
        await expect(this.page.locator('//input[contains(@id,"edit-field-photo-selection-0-remove-button")]')).toBeVisible();
        await expect(this.page.locator('//input[contains(@id,"edit-field-photo-selection-0-remove-button")]')).toBeEnabled();
    }

    //verify Gallery image is uploaded 
    async verifyGalleryImagesUploaded()
    {
        await this.testSteps.LogInfo('Verifying First Image is uploaded');
        // looing for 'x' button to remove to ensure it has been properly uploaded
        await expect(this.page.locator('//input[contains(@id,"edit-field-gallery-images-selection-0-remove-button")]')).toBeVisible();
        await expect(this.page.locator('//input[contains(@id,"edit-field-gallery-images-selection-0-remove-button")]')).toBeEnabled();

        await this.testSteps.LogInfo('Verifying Second Image is uploaded');
        // looing for 'x' button to remove to ensure it has been properly uploaded
        await expect(this.page.locator('//input[contains(@id,"edit-field-gallery-images-selection-1-remove-button")]')).toBeVisible();
        await expect(this.page.locator('//input[contains(@id,"edit-field-gallery-images-selection-1-remove-button")]')).toBeEnabled();

        await this.testSteps.LogInfo('Verifying Third Image is uploaded');
        // looing for 'x' button to remove to ensure it has been properly uploaded
        await expect(this.page.locator('//input[contains(@id,"edit-field-gallery-images-selection-2-remove-button")]')).toBeVisible();
        await expect(this.page.locator('//input[contains(@id,"edit-field-gallery-images-selection-2-remove-button")]')).toBeEnabled();

        await this.testSteps.LogInfo('Verifying Fourth Image is uploaded');
        // looing for 'x' button to remove to ensure it has been properly uploaded
        await expect(this.page.locator('//input[contains(@id,"edit-field-gallery-images-selection-3-remove-button")]')).toBeVisible();
        await expect(this.page.locator('//input[contains(@id,"edit-field-gallery-images-selection-3-remove-button")]')).toBeEnabled();

        await this.testSteps.LogInfo('Verifying Fifth Image is uploaded');
        // looing for 'x' button to remove to ensure it has been properly uploaded
        await expect(this.page.locator('//input[contains(@id,"edit-field-gallery-images-selection-4-remove-button")]')).toBeVisible();
        await expect(this.page.locator('//input[contains(@id,"edit-field-gallery-images-selection-4-remove-button")]')).toBeEnabled();
    }

    //verify Gallery image Edited uploaded 
    async verifyGalleryImagesUploadedEdited()
    {
        await this.testSteps.LogInfo('Verifying First Image is uploaded');
        // looing for 'x' button to remove to ensure it has been properly uploaded
        await expect(this.page.locator('//input[contains(@id,"edit-field-gallery-images-selection-0-remove-button")]')).toBeVisible();
        await expect(this.page.locator('//input[contains(@id,"edit-field-gallery-images-selection-0-remove-button")]')).toBeEnabled();
    }

    //verify Attachment is uploaded 
    async verifyRemoteVideoUploaded()
    {
        await this.testSteps.LogInfo('Verifying Remote video is uploaded');

        // looing for 'x' button to remove to ensure it has been properly uploaded
        await expect(this.page.locator('//input[contains(@id,"edit-field-video-selection-0-remove-button")]')).toBeVisible();
        await expect(this.page.locator('//input[contains(@id,"edit-field-video-selection-0-remove-button")]')).toBeEnabled();
    }


}