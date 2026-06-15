import { Page, expect } from '@playwright/test';
import { TestSetUpData, TestData } from '../../test-data/TestDataObject';
import { ModerationSideBar } from '@poms/base-pages/ModerationSideBar';
import { ApplicationNodePage } from '@poms/content-pages/Application/ApplicationNodePage';
import { RevisionPage } from '@poms/base-pages/RevisionPage';
import { ApplicationComparePage } from '@poms/content-pages/Application/ApplicationComparePage';
import { DeleteRevisionsPage } from '@poms/base-pages/DeleteRevisionsPage';
import { RevertRevisionsPage } from '@poms/base-pages/RevertRevisionsPage';
import { UploadMedia } from '@poms/base-pages/UploadMedia';
import { GalleryImageValues } from '@poms/base-pages/UploadMedia';

export interface UploadState
{
    original: boolean;
    edited: boolean;
};

export interface GalleryUploadState
{
    gallery: boolean;
    galleryEdit: boolean;
};

export class UploadMediaHelper
{
    // pages
    private readonly moderationSideBar: ModerationSideBar;
    private readonly applicationNodePage: ApplicationNodePage;
    private readonly revisionPage: RevisionPage;
    private readonly applicationComparePage: ApplicationComparePage;
    private readonly deleteRevisionsPage: DeleteRevisionsPage;
    private readonly revertRevisionsPage: RevertRevisionsPage;
    private readonly uploadMedia: UploadMedia;

    constructor(
        private page: Page,
        // isolated instances of test data via constructor
        private testSetUpData: typeof TestSetUpData,
        private testdata: typeof TestData
    )
    {
        // imported pages
        this.moderationSideBar = new ModerationSideBar(page, this.testSetUpData, this.testdata);
        this.applicationNodePage = new ApplicationNodePage(page, this.testSetUpData, this.testdata);
        this.revisionPage = new RevisionPage(page);
        this.applicationComparePage = new ApplicationComparePage(page, this.testSetUpData, this.testdata);
        this.deleteRevisionsPage = new DeleteRevisionsPage(page);
        this.revertRevisionsPage = new RevertRevisionsPage(page);
        this.uploadMedia = new UploadMedia(page, this.testSetUpData, this.testdata);
    }

    // upload Attachment
    async uploadAttachmentWorkflow(options: UploadState)
    {
        if (options.original)
        {
            await this.uploadMedia.clickAddMediaAttachmentButton();
            await this.uploadMedia.uploadAttachmnetProcess(this.testdata.Media.attachmentFileName);
            await this.uploadMedia.enterMediaName(this.testdata.Media.attachmentName);
        }
        else
        {
            await this.uploadMedia.clickEditPageAddMediaAttachmentButton();
            await this.uploadMedia.uploadAttachmnetProcess(this.testdata.Media.attachmentFileNameEdited);
            await this.uploadMedia.enterMediaName(this.testdata.Media.attachmentNameEdited);
        }
        await this.uploadMedia.clickMediaSave();
        await this.uploadMedia.clickInsertSelectedButton();
        await this.uploadMedia.verifyAttachmentUploaded();
    }

    // upload image workflow
    async uploadImageWorkflow(options: UploadState)
    {
        if (options.original)
        {
            await this.uploadMedia.clickAddMediaImageButton();
            await this.uploadMedia.uploadImageProcess(this.testdata.Media.imageFileName);
            await this.uploadMedia.enterImageValues(this.testdata.Media.imageAltText, this.testdata.Media.imageTitle, this.testdata.Media.imageCaption, this.testdata.Media.imageName);
        }

        if (options.edited)
        {
            await this.uploadMedia.clickEditPageAddMediaImageButton();
            await this.uploadMedia.uploadImageProcess(this.testdata.Media.imageFileNameEdited);
            await this.uploadMedia.enterMediaName(this.testdata.Media.imageNameEdited);
        }

        await this.uploadMedia.clickMediaSave();
        await this.uploadMedia.clickInsertSelectedButton();
        await this.uploadMedia.verifyImageUploaded();
    }

    // upload image workflow
    async uploadGalleryImageWorkflow(galleryOptions: GalleryUploadState, details: GalleryImageValues[])
    {
        if (galleryOptions.gallery && details.length !== 5)
        {
            throw new Error(`Expected 5 gallery images, but got ${details.length}`);
        }

        if (galleryOptions.gallery)
        {
            await this.uploadMedia.clickAddMediaImageButton();
            await this.uploadMedia.uploadMultipleGalleryImagesProcess(this.testdata.Media.galleryImage1FileName, this.testdata.Media.galleryImage2FileName,
                this.testdata.Media.galleryImage3FileName, this.testdata.Media.galleryImage4FileName, this.testdata.Media.galleryImage5FileName
            );
            await this.uploadMedia.fillMultipleGalleryImageDetails(details);
            await this.uploadMedia.clickMediaSave();
            await this.uploadMedia.clickInsertSelectedButton();
            await this.uploadMedia.verifyGalleryImagesUploaded();
        }

        if (galleryOptions.galleryEdit)
        {
            await this.uploadMedia.clickEditPageAddMediaImageButton();
            await this.uploadMedia.uploadImageProcess(this.testdata.Media.imageFileNameEdited);
            await this.uploadMedia.enterMediaName(this.testdata.Media.galleryImage1NameEdited);
            await this.uploadMedia.clickMediaSave();
            await this.uploadMedia.clickInsertSelectedButton();
            await this.uploadMedia.verifyGalleryImagesUploadedEdited();
        }

    }

    // upload remote video 
    async uploadRemoteVideo(options: UploadState)
    {
        if (options.original)
        {
            await this.uploadMedia.clickAddMediaRemoteVideoButton();
            await this.uploadMedia.addRemoteVideoLinkProcess(this.testdata.Media.remoteVideoURL);
            await this.page.getByRole('button', { name: 'Add', exact: true }).click();
        }
        else
        {
            await this.uploadMedia.clickEditPageAddMediaRemoteVideoButton();
            await this.uploadMedia.addRemoteVideoLinkProcess(this.testdata.Media.remoteVideoURLEdited);
            await this.page.getByRole('button', { name: 'Add', exact: true }).click();
        }
        await this.uploadMedia.clickMediaSave();
        await this.uploadMedia.clickInsertSelectedButton();
        await this.uploadMedia.verifyRemoteVideoUploaded();
    }
}