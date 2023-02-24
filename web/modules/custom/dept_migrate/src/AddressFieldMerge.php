<?php

namespace Drupal\dept_migrate;

/**
 * Class AddressFieldMerge.
 *
 * Gathers arbitary address fragments and assembles into array
 * that AddressField migrate process plugin can understand and work with.
 */
class AddressFieldMerge {

  /**
   * Transform to adddressfield array.
   *
   * Take an array of address fragments (opinionated) and
   * return a structured addressfield plugin array.
   *
   * @param array $addressFragments
   *   Order matters:
   *   - Address line 1.
   *   - Address line 2.
   *   - Address line 3.
   *   - Address line 4.
   *   - Address line 5.
   *   - Administrative area/county.
   *   - Town/city.
   *   - Postal code.
   *   - Country code.
   *
   * @return array
   *   Array of keyed data for use with the addressfield process plugin.
   */
  public static function convertToAddressFieldFormat(array $addressFragments) {

    // Flatten down/extract any contained array values.
    for ($i = 0; $i < count($addressFragments); $i++) {
      if (empty($addressFragments[$i])) {
        $addressFragments[$i] = '';
      }

      if (is_array($addressFragments[$i])) {
        $addressFragments[$i] = array_pop($addressFragments[$i])['value'];
      }
    }

    $address_line1 = $addressFragments[0];
    $address_line2 = $addressFragments[1];
    $address_line3 = $addressFragments[2];
    $address_line4 = $addressFragments[3];
    $address_line5 = $addressFragments[4];
    $admin_area_county = $addressFragments[5];
    $town_city = $addressFragments[6];
    $postal_code = $addressFragments[7];
    $country_code = empty($addressFragments[8]) ? 'GB' : $addressFragments[8];

    // Flatten ambiguous address fields:
    // Always merge together address lines 1 and 2.
    $flattened_addressline1 = '';
    $flattened_addressline1 = $address_line1;
    if (!empty($address_line2)) {
      $flattened_addressline1 .= ', ' . $address_line2;
    }

    if (strlen($flattened_addressline1) >= 255) {
      $flattened_addressline1 = substr($flattened_addressline1, 0, 255);
    }

    // ... and 3, 4 and 5.
    $flattened_addressline2 = '';
    if (!empty($address_line3)) {
      $flattened_addressline2 = $address_line3;
    }
    if (!empty($address_line4)) {
      $flattened_addressline2 .= ', ' . $address_line4;
    }
    if (!empty($address_line5)) {
      $flattened_addressline2 .= ', ' . $address_line5;
    }

    if (strlen($flattened_addressline2) >= 255) {
      $flattened_addressline2 = substr($flattened_addressline2, 0, 255);
    }

    // Array labels mimic the D7 addressfield values.
    // See Drupal\address\Plugin\migrate\process\AddressField::transform().
    $address_data = [
      'country' => $country_code,
      'administrative_area' => $admin_area_county,
      'locality' => $town_city,
      'dependent_locality' => '',
      'postal_code' => $postal_code,
      'thoroughfare' => $flattened_addressline1,
      'premise' => $flattened_addressline2,
      'organisation_name' => '',
    ];

    return $address_data;
  }

}
