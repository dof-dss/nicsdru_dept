import { GalleryImageValues } from "@poms/base-pages/UploadMedia";

export const TestSetUpData = {
  // test data to be overridden in tests 
  urlForTest: { url: '' },
  userForTest: { username: '', password: '' },
  contentTypeforTest: { contentType: '' },
  contentTitleforTest: { contentTitle: '' },
  saveAsOptionForTest: { saveAsOption: '' },
  moderationStateForTest: { moderationState: '' },
  previewContentForTest: { preview: false },
  globalTopicForTest: { globalTopic: '' },

  // valid urls 
  validTestURLList: {
    finance_url: process.env.FINANCE_URL!,
    communities_url: process.env.COMMUNITIES_URL!,
    daera_url: process.env.DAERA_URL!,
    economy_url: process.env.ECONOMY_URL!,
    education_url: process.env.EDUCATION_URL!,
    executive_office_url: process.env.EXECUTIVE_OFFICE_URL!,
    health_url: process.env.HEALTH_URL!,
    dfi_url: process.env.DFI_URL!,
    nigov_url: process.env.NIGOV_URL!,
    justice_url: process.env.JUSTICE_URL!
  },

  // valid user list
  validUserList: {
    author_username: process.env.AUTHOR_USERNAME!,
    author_password: process.env.AUTHOR_PASSWORD!,
    supervisor_username: process.env.SUPERVISOR_USERNAME!,
    supervisor_password: process.env.SUPERVISOR_PASSWORD!,
    stats_author_username: process.env.STATS_AUTHOR_USERNAME!,
    stats_author_password: process.env.STATS_AUTHOR_PASSWORD!,
    stats_supervisor_username: process.env.STATS_SUPERVISOR_USERNAME!,
    stats_supervisor_password: process.env.STATS_SUPERVISOR_PASSWORD!,
    topicsupervisor_username: process.env.TOPICSUPERVISOR_USERNAME!,
    topicsupervisor_password: process.env.TOPICSUPERVISOR_PASSWORD!
  },

  // valid content type list
  validContentTypeList: {
    application: 'Application',
    article: 'Article',
    articleCKEditorFull: 'Article CKEditor Full',
    articleCKEditorImportWord: 'Article CKEditor Import Word',
    consultation: 'Consultation',
    consultationFutureDate: 'Consultation Future Date',
    contact: 'Contact',
    event: 'Event',
    gallery: 'Gallery',
    heritagesite: 'Heritage site',
    link: 'Link',
    news: 'News',
    profile: 'Profile',
    protectedarea: 'Protected area',
    publication: 'Publication',
    publicationExternalLink: 'Publication External Link',
    securePublication: 'Secure Publication',
    subtopic: 'Subtopic',
    topic: 'Topic',
    unlawfully: 'Unlawfully at large'
  },

  // valid save as option list
  validSaveAsOptionList: {
    draft: 'draft',
    needsreview: 'needs_review',
    published: 'published',
    archived: 'archived',
  },

  // valid Moderation states
  validModerationStates: {
    draft: 'Draft',
    needsreview: 'Needs Review',
    published: 'Published',
    archived: 'Archived',
    deleted: 'Deleted'
  }

};

// Logic for dynamic dates should be inside a function or calculated once
const getTimestamp = () => new Date().toISOString().replace(/[^0-9]/g, "").slice(0, 16);

const startDate = new Date();
// Format to YYYY-MM-DD
const formattedStartDate = startDate.toISOString().split('T')[0];

const startDateEdited = new Date();
// adding 7 days to todays date
startDateEdited.setDate(startDateEdited.getDate() + 2);
// Format to YYYY-MM-DD
const formattedStarDateEdited = startDateEdited.toISOString().split('T')[0];

const endDate = new Date();
// adding 7 days to todays date
endDate.setDate(endDate.getDate() + 7);
// Format to YYYY-MM-DD
const formattedEndDate = endDate.toISOString().split('T')[0];

const endDateEdited = new Date();
// adding 7 days to todays date
endDateEdited.setDate(endDateEdited.getDate() + 14);
// Format to YYYY-MM-DD
const formattedEndDateEdited = endDateEdited.toISOString().split('T')[0];

const futureStartDate = new Date();
// adding 7 days to todays date
futureStartDate.setDate(futureStartDate.getDate() + 3);
// Format to YYYY-MM-DD
const formattedFutureStartDate = futureStartDate.toISOString().split('T')[0];


///////////////////////////////////////////////////////////////////////////

// verification date times 

const verifyStartDate = new Date();
const verifyStartDateFormated = verifyStartDate.toLocaleDateString('en-GB', {
  day: 'numeric',
  month: 'long',
  year: 'numeric'
});

const verifyEndDate = new Date();
verifyEndDate.setDate(verifyEndDate.getDate() + 7);
const verifyEndDateFormated = verifyEndDate.toLocaleDateString('en-GB', {
  day: 'numeric',
  month: 'long',
  year: 'numeric'
});

const verifyFutureStartDateFormat = new Date();
verifyFutureStartDateFormat.setDate(verifyFutureStartDateFormat.getDate() + 3);
const verifyFutureStartDateFormated = verifyFutureStartDateFormat.toLocaleDateString('en-GB', {
  day: 'numeric',
  month: 'long',
  year: 'numeric'
});

export const TestData = {
  get dateTime() { return new Date().toISOString(); },



  Media: {
    // attachment 1 values
    attachmentFileName: 'AutomationTesting.pdf',
    attachmentFileNameEdited: 'AutomationTestingEdited.pdf',

    // image 1 values 
    imageFileName: 'TestingImage.jpg',
    imageAltText: '',
    imageTitle: '',
    imageCaption: '',
    imageName: 'Automated image Testing New',

    // image 2 name has no other values as this tests that the other fields are not mandatory
    imageFileNameEdited: 'TestingImageEdited.png',
    imageNameEdited: 'Automated attachement Testing Edited',

    // gallery image values
    galleryImage1FileName: 'TestGallery.jpg',
    galleryImageAltText: 'Gallery Image 1 alt text',
    galleryImageTitle: 'Gallery Image 1 Title',
    galleryImageCaption: 'This is Gallery Image 1 Caption',
    galleryImageName: 'Automated gallery image 1 Testing New',

    // gallery image 2 values
    galleryImage2FileName: 'TestGallery2.jpg',
    galleryImage2AltText: 'Gallery Image 2 alt text',
    galleryImage2Title: 'Gallery Image 2 Title',
    galleryImage2Caption: 'This is Gallery Image 2 Caption',
    galleryImage2Name: 'Automated gallery image 2  Testing New',

    // gallery image 3 values
    galleryImage3FileName: 'TestGallery3.jpg',
    galleryImage3AltText: 'Gallery Image 3 alt text',
    galleryImage3Title: 'Gallery Image 3 Title',
    galleryImage3Caption: 'This is Gallery Image 3 Caption',
    galleryImage3Name: 'Automated gallery image 3  Testing New',

    // gallery image 4 values
    galleryImage4FileName: 'TestGallery4.jpg',
    galleryImage4AltText: 'Gallery Image 4 alt text',
    galleryImage4Title: 'Gallery Image 4 Title',
    galleryImage4Caption: 'This is Gallery Image 4 Caption',
    galleryImage4Name: 'Automated gallery image 4 Testing New',

    // gallery image 5 values
    galleryImage5FileName: 'TestGallery5.jpg',
    galleryImage5AltText: 'Gallery Image 5 alt text',
    galleryImage5Title: 'Gallery Image 5 Title',
    galleryImage5Caption: 'This is Gallery Image 5 Caption',
    galleryImage5Name: 'Automated gallery image 5 Testing New',

    // Gallery image edited name has no other values as this tests that the other fields are not mandatory
    galleryImage1FileNameEdited: 'TestingImageEdited.png',
    galleryImage1NameEdited: 'Gallery image edited',

    BannerImageFileName: 'BannerImage.jpg',
    BannerImageFileNameEdited: 'BannerImageEdited.jpg',
    BannerOverlayImageFileName: 'BannerOverlayImage.jpg',
    BannerOverlayImageFileNameEdited: 'BannerOverlayImageEdited.jpg',
    BannerImageThinFileName: 'BannerThinImage.jpg',
    BannerImageThinFileNameEdited: 'BannerThinImageEdited.jpg',

    remoteVideoURL: 'https://www.youtube.com/watch?v=thZ21emNdns',
    remoteVideoURLEdited: 'https://www.youtube.com/watch?v=H6k77SXU6cU',

    attachmentName: 'Automated attachment Testing New',
    attachmentNameEdited: 'Automated attachement Testing Edited',

    bannerName: 'Automated attachment Testing New',
    bannerNameEdited: 'Automated attachement Testing Edited',
    bannerOverlayName: 'Automated attachment Testing New',
    bannerOverlayNameEdited: 'Automated attachement Testing Edited',
    bannerThinName: 'Automated attachment Testing New',
    bannerThinNameEdited: 'Automated attachement Testing Edited',

    audioFile: 'AutomationAudio.wav',
    audioFileName: 'Automation Audio File Wav',

    wordFile: 'ImportFromWord.docx',
  },

  GlobalTopics: {
    employment: 'Employment',
    energy: 'Energy',
    environment: 'Environment'
  },

  SiteTopics: {
    topic1: '',
    topic2: null,
    topic3: null,
    topic4: null
  } as Record<string, string | null>,

  Application: {
    title: 'Automated Test - New sample Application title' + ' - ' + getTimestamp(),
    titleEdited: 'Automated Test - Edited sample Application title' + ' - ' + getTimestamp(),
    revisionlog: 'This is an automated revision log message',
    revisionlogEdited: 'This is an edited automated revision log message',
    summary: 'This is new application summary',
    summaryEdited: 'This is edited application summary',
    additionalinfo: 'This is new application additional information',
    additionalinfoEdited: 'This is edited application additional information',
    beforeyoustart: 'This is new application before you start',
    beforeyoustartEdited: 'This is edited application before you start',
    LinkURL: 'Professional medical and environmental health advice',
    LinkURLEdited: 'https://www.nidirect.gov.uk',
    LinkText: 'Professional medical and environmental health advice',
    LinkTextEdited: 'NI Direct External'
  },

  Article: {
    title: 'Automated Test - New sample Article title' + ' - ' + getTimestamp(),
    titleEdited: 'Automated Test - Edited sample Article title' + ' - ' + getTimestamp(),
    revisionlog: 'This is an automated revision log message',
    revisionlogEdited: 'This is an edited automated revision log message',
    summary: 'This is a new Article summary',
    summaryEdited: 'This is a edited Article summary',
    body: 'This is a new article body content',
    bodyEdited: 'This is a edited article body content',
  },

  Consultation: {
    title: 'Automated Test - New sample Consultation title' + ' - ' + getTimestamp(),
    titleEdited: 'Automated Test - Edited sample Consultation title' + ' - ' + getTimestamp(),
    datePublished: '2025-12-31',
    revisionlog: 'This is an automated revision log message',
    revisionlogEdited: 'This is an edited automated revision log message',
    summary: 'This is new application summary',
    summaryEdited: 'This is edited application summary',
    startDate: formattedStartDate,
    startDateEdited: '2025-12-31',
    startTime: '00:00:00',
    startTimeEdited: '10:00:00',
    endDate: formattedEndDate,
    endDateEdited: '2025-12-31',
    endTime: '23:59:59',
    endTimeEdited: '17:00:00',
    body: 'This is new consultation body field',
    bodyEdited: 'This is edited consultation body field',
    respondeOnline: 'Public appointments - Certification Officer for Northern Ireland',
    respondeOnlineEdited: 'https://www.nidirect.gov.uk',
    emailAddress: 'test@test.com',
    emailAddressEdited: 'automated@test.com',
    postalAddress: 'Stormont Estate, Upper Newtownards Road, Belfast, BT4 3SH',
    postalAddressEdited: 'NICS Library Service, Craigantlet Buildings, Stoney Road, Belfast, BT4 3SX',
    verifyStartDateAndTime: verifyStartDateFormated + ', ' + '12.00 am',
    verifyEndDateAndTime: verifyEndDateFormated + ', ' + '11.59 pm',
    futureStartDate: formattedFutureStartDate,
    verifyFutureStartDate: verifyFutureStartDateFormated + ', ' + '12.00 am',
  },

  Contact: {
    title: 'Automated Test - New sample Contact title' + ' - ' + getTimestamp(),
    titleEdited: 'Automated Test - Edited sample Contact title' + ' - ' + getTimestamp(),
    revisionlog: 'This is an automated revision log message',
    revisionlogEdited: 'This is an edited automated revision log message',
    body: 'This is new contact body field',
    bodyEdited: 'This is edited contact body field',
    mapName: 'Belfast City Hall',
    mapLatitude: '54.5966',
    mapLongitude: ' -5.9299',
    mapNameEdited: 'Stormount',
    mapLocationModalName: 'Stormount',
  },

  Event: {
    title: 'Automated Test - New sample Event title' + ' - ' + getTimestamp(),
    titleEdited: 'Automated Test - Edited sample Event title' + ' - ' + getTimestamp(),
    revisionlog: 'This is an automated revision log message',
    revisionlogEdited: 'This is an edited automated revision log message',
    startDate: formattedStartDate,
    startDateEdited: '2025-12-31',
    startTime: '00:00:00',
    startTimeEdited: '10:00:00',
    endDate: formattedEndDate,
    endDateEdited: '2025-12-31',
    endTime: '23:59:59',
    endTimeEdited: '17:00:00',
    Region: 'Belfast',
    regionEdited: 'Virtual',
    Summary: 'This is new Event summary',
    SummaryEdited: 'This is edited Event summary',
    description: 'This is a new Event description',
    descriptionEdited: 'This is an edited Event description',
    HostedBy: 'Department of Justice',
    HostedByEdited: 'NICS Events Team',
    venue: 'Online',
    venueEdited: 'Belfast City Hall',
    registrationLink: 'Help viewing documents',
    registrationLinkEdited: 'https://www.nidirect.gov.uk',
    LinkText: 'New Virtual NICS Events',
    LinkTextEdited: 'Edited NICS Events External',
    verifyStartDateAndTime: verifyStartDateFormated + ' ' + '12:00 am',
    verifyStartDateEditedAndTime: '31 December 2025 10:00 am',
    verifyEndDateAndTime: verifyEndDateFormated + ' ' + '11:59 pm',
    verifyEndDateEditedAndTime: '31 December 2025 5:00 pm',
  },

  HeritageSite: {
    title: 'Automated Test - New sample Heritage Site title' + ' - ' + getTimestamp(),
    titleEdited: 'Automated Test - Edited sample Heritage Site title' + ' - ' + getTimestamp(),
    revisionlog: 'This is an automated revision log message',
    revisionlogEdited: 'This is an edited automated revision log message',
  },

  Gallery: {
    title: 'Automated Test - New sample Gallery title' + ' - ' + getTimestamp(),
    titleEdited: 'Automated Test - Edited sample Gallery title' + ' - ' + getTimestamp(),
    revisionlog: 'This is an automated revision log message',
    revisionlogEdited: 'This is an edited automated revision log message',
    summary: 'This is a new Gallery summary',
    summaryEdited: 'This is a edited Gallery summary',
    body: 'This is a new Gallery body content',
    bodyEdited: 'This is a edited Gallery body content',
  },

  Link: {
    title: 'Automated Test - New sample Publication title' + ' - ' + getTimestamp(),
    titleEdited: 'Automated Test - Edited sample Publication title' + ' - ' + getTimestamp(),
    datePublished: '2025-12-31',
    revisionlog: 'This is an automated revision log message',
    revisionlogEdited: 'This is an edited automated revision log message',
  },


  News: {
    title: 'Automated Test - New sample News title' + ' - ' + getTimestamp(),
    titleEdited: 'Automated Test - Edited sample News title' + ' - ' + getTimestamp(),
    revisionlog: 'This is an automated revision log message',
    revisionlogEdited: 'This is an edited automated revision log message',
    newsType: 'news',
    newsTypeEdited: 'pressrelease',
    introductoryParagraph: 'This is a new introductory paragraph',
    introductoryParagraphEdited: 'This is a edited introductory paragraph',
    publicationDateEdited: '2025-12-31',
    teaser: 'This is a new news teaser',
    teaserEdited: 'This is a edited news teaser',
    body: 'This is new news body field',
    bodyEdited: 'This is edited news body field',
    notesToEditor: 'This is a new notes to editor',
    notesToEditorEdited: 'This is a edited notes to editor'
  },


  ProtectedArea: {
    title: 'Automated Test - New sample Publication title' + ' - ' + getTimestamp(),
    titleEdited: 'Automated Test - Edited sample Publication title' + ' - ' + getTimestamp(),
    datePublished: '2025-12-31',
    revisionlog: 'This is an automated revision log message',
    revisionlogEdited: 'This is an edited automated revision log message',
  },


  Publication: {
    title: 'Automated Test - New sample Publication title' + ' - ' + getTimestamp(),
    titleEdited: 'Automated Test - Edited sample Publication title' + ' - ' + getTimestamp(),
    datePublished: '2025-12-31',
    revisionlog: 'This is an automated revision log message',
    revisionlogEdited: 'This is an edited automated revision log message',
    lastUpdatedDateEdited: formattedStartDate,
    lastUpdatedTimeEdited: '00:00:00',
    publicationType: 'Circulars',
    publicationTypeEdited: 'Agendas and minutes',
    summary: 'This is new Publication summary',
    summaryEdited: 'This is edited Publication summary',
    body: 'This is new news body field',
    bodyEdited: 'This is edited news body field',
    externalPublication: 'https://www.nidirect.gov.uk',
    linkTextPubllication: 'NI Direct',
    externalPublicationEdited: 'https://www.bbc.co.uk',
    linkTextPubllicationEdited: 'BBC News',
    verifyDatePublished: verifyStartDateFormated,
    verifyLastUpdatedDate: verifyStartDateFormated,
  },

  SecurePublication: {
    title: 'Automated Test - New sample Publication title' + ' - ' + getTimestamp(),
    titleEdited: 'Automated Test - Edited sample Publication title' + ' - ' + getTimestamp(),
    datePublished: '2025-12-31',
    revisionlog: 'This is an automated revision log message',
    revisionlogEdited: 'This is an edited automated revision log message',
    lastUpdatedDateEdited: formattedStartDate,
    lastUpdatedTimeEdited: '00:00:00',
    publicationType: 'Circulars',
    publicationTypeEdited: 'Agendas and minutes',
    summary: 'This is new Publication summary',
    summaryEdited: 'This is edited Publication summary',
    body: 'This is new news body field',
    bodyEdited: 'This is edited news body field',
    externalPublication: 'https://www.nidirect.gov.uk',
    linkTextPubllication: 'NI Direct',
    externalPublicationEdited: 'https://www.bbc.co.uk',
    linkTextPubllicationEdited: 'BBC News',
    verifyDatePublished: verifyStartDateFormated,
    verifyLastUpdatedDate: verifyStartDateFormated,
  },

  Subtopic: {
    title: 'Automated Test - New sample Subtopic title' + ' - ' + getTimestamp(),
    summary: 'This is an application summary',
    longDescription: 'This is application additional information',
  },

  Topic: {
    title: 'Automated Test - New sample Topic title' + ' - ' + getTimestamp(),
    summary: 'This is an application summary',
    longDescription: 'This is application additional information'
  },

  Unlawfully: {
    title: 'Automated Test - New sample unlawfully and large title' + ' - ' + getTimestamp(),
    titleEdited: 'Automated Test - Edited sample unlawfully and large title' + ' - ' + getTimestamp(),
    revisionlog: 'This is an automated revision log message',
    revisionlogEdited: 'This is an edited automated revision log message',
    uALFrom: '2025-12-31',
    uALFromEdited: '2024-11-01',
    age: '30',
    ageEdited: '29',
    prison: 'HMP Maghaberry',
    prisonEdited: 'HMP Hydebank Wood',
    offence: 'Burglary',
    offenceEdited: 'Thief',
    description: 'This is a new description of the unlawfully at large individual',
    descriptionEdited: 'This is an edited description of the unlawfully at large individual',
    eyeColour: 'Blue',
    eyeColourEdited: 'Green',
    hairColour: 'Blonde',
    hairColourEdited: 'Black',
    distinguishingMarks: 'This is a new distinguishing mark of the unlawfully at large individual',
    distinguishingMarksEdited: 'This is an edited distinguishing mark of the unlawfully at large individual',
    releaseType: 'Automatic',
    releaseTypeEdited: 'Standard',
  }
};

export const galleryImageDetails: GalleryImageValues[] = Array.from({ length: 5 }, (_, i) => ({
  alt: `Gallery Image ${i + 1} alt text`,
  title: `Gallery Image ${i + 1} Title`,
  caption: `This is Gallery Image ${i + 1} Caption`,
  name: `Automated gallery image ${i + 1} Testing`,
}));