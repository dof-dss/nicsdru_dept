/**
 * Concatenate a site hostname and a pathname.
 *
 * @param {string} site_id
 *   The site id.
 * @param {string} pathname
 *   The relative path to append to DRUPAL_TEST_BASE_URL
 * @param {function} callback
 *   A callback which will be called.
 * @return {object}
 *   The 'browser' object.
 */
exports.command = function siteURL(site_id, pathname, callback) {
  const self = this;
  const siteUrl = process.env.DRUPAL_TEST_BASE_URL.replace('dept', site_id);
  this.url(`${siteUrl}${pathname}`);

  if (typeof callback === 'function') {
    callback.call(self);
  }
  return this;
};
