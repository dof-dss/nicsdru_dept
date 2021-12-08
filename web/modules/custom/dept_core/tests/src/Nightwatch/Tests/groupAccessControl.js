module.exports = {
  '@tags': ['dept_core'],

  'Test access on node known to be shared between two sites': browser => {

    // Resize default window size.
    browser.resizeWindow(1600, 1024);

    // Visible on NIGOV
    browser
      .siteURL('nigov', '/node/9962')
      .waitForElementVisible('body', 1000)
      .expect.element('h1.page-title').text.to.not.contain('Access denied');


    // Access denied on DAERA.
    browser
      .siteURL('daera-ni', '/node/9962')
      .waitForElementVisible('body', 1000)
      .expect.element('h1.page-title').text.to.contain('Access denied');
  },

};
