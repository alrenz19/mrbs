<?php
namespace MRBS;

// Returns a string containing a single line of data details, or if $as_html is set
// a table row of details.   The first column is the $label and the second column
// the $value.   $class is an optional class name which can be applied to the
// second column.  If $email is set then the $value will be turned into a mailto: link.
function create_details_row(string $label, ?string $value, bool $as_html=false, string $class='', ?string $email=null) : string
{
  $value = $value ?? '';
  $result = '';

  if ($as_html)
  {
    // 🔧 Don't escape if it's meant to be raw HTML
    $escaped_value = $value;

    $result .= "<tr>\n";
    $result .= "<td>$label</td>\n";
    $result .= "<td" . ((!empty($class)) ? " class=\"$class\"" : '') . '>';

    if (isset($email)) {
      $html = '<a href="mailto:' . rawurlencode($email) . '">' . htmlspecialchars($value) . '</a>';
      $result .= '<span class="contact" data-html="' . base64_encode($html) . '">';
    }

    $result .= $escaped_value;

    if (isset($email)) {
      $result .= '</span>';
    }

    $result .= "</td>\n";
    $result .= "</tr>\n";
  }
  else
  {
    $result .= str_replace('&nbsp;', ' ', $label) . ": $value\n";
  }

  return $result;
}



// Returns a string containing a set of details for $data consisting of a label/value
// pair for each data element in the array $data.   If $as_html is true then the string
// is the HTML for a table body, ie looks like "<tbody> ... </tbody>".
//    $keep_private   boolean   if true then any private fields will be given the class 'private';
//                              note that $data must already have had values substituted
//                              for private fields
//    $room_disabled  boolean   if true then a note will be added that the room is disabled
function create_details_body(array $data, bool $as_html=false, bool $keep_private=false, bool $room_disabled=false) : string
{
  global $enable_periods, $confirmation_enabled, $approval_enabled;
  global $is_private_field, $standard_fields;
  global $datetime_formats;
  global $select_options, $booking_types;
  global $edit_entry_field_order;
  global $auth;

  // Get the duration if we haven't got it already
  if (!isset($data['duration']))
  {
    // We will translate the units later
    $d = get_duration($data['start_time'], $data['end_time'], $enable_periods, $data['area_id'], false);
    $data['duration'] = $d['duration'];
    $data['dur_units'] = $d['dur_units'];
  }

  // Set a rep_type if it hasn't been
  if (!isset($data['rep_type']))
  {
    $data['rep_type'] = REP_NONE;
  }

  // Go through each of the columns and for each of them that can be made private
  // substitute the private text if the user is not allowed to see the data
  $private_text = "[" . get_vocab("unavailable") . "]";

  foreach ($data as $key => $value)
  {
    $hide_this_field = $keep_private && isset($is_private_field["entry.$key"]) && $is_private_field["entry.$key"];
    // We could just test each column against $is_private_field["entry.$key"]
    // but restricting the test to the columns below guards against the possibility
    // that somebody has accidentally configured a 'system' field to be private
    switch ($key)
    {
      case 'name':
      case 'description':
      case 'room_name':
      case 'area_name':
      case 'room_id':
      case 'entry_info_time':
      case 'entry_info_user':
      case 'entry_info_text':
      case 'repeat_info_time':
      case 'repeat_info_user':
      case 'repeat_info_text':
        $data[$key] = $hide_this_field ? $private_text : $data[$key];
        break;

      case 'type':
        $data[$key] = $hide_this_field ? $private_text : get_type_vocab($data[$key]);
        break;

      case 'create_by':
      case 'modified_by':
        if ($hide_this_field)
        {
          $data[$key] = $private_text;
        }
        else
        {
          $user = auth()->getUser($data[$key]);
          $data[$key] = (isset($user)) ? $user->display_name : '';
        }
        // Add in the email address if there is one and we are allowed to
        if (!isset($user) ||
            ($user->email === '') ||
            // The field must not be private
            $hide_this_field ||
            // The user must be logged in, so that we don't expose email addresses to the public
            (null === session()->getCurrentUser()) ||
            // MRBS must be configured to allow ordinary users to see others' details
            ($auth['only_admin_can_see_other_users'] && !is_admin()))
        {
          $email[$key] = null;
        }
        else
        {
          $email[$key] = $user->email;
        }
        break;

      default:
        if (!in_array($key, $standard_fields['entry']))
        {
          $data[$key] = $hide_this_field ? $private_text : $data[$key];
        }
        break;
    }
  }

  $fields = db()->field_info(_tbl('entry'));

  // Get the order in which to display fields
  $field_order = (isset($edit_entry_field_order)) ? $edit_entry_field_order : array();
  foreach ($fields as $field)
  {
    if (!in_array($field['name'], $field_order))
    {
      $field_order[] = $field['name'];
    }
  }

  // Add in the two special status fields
  foreach (array('approval_status', 'confirmation_status') as $key)
  {
    if (!in_array($key, $field_order))
    {
      array_push($field_order, $key);
    }
  }

  $tbody = '';
  $tbody .= ($as_html) ? "<tbody>\n" : "";

  foreach ($field_order as $key)
  {
    switch ($key)
    {
      // Ignore these
      case 'name':  // in the heading
      case 'id':
      case 'entry_type':
      case 'reminded':
      case 'repeat_id':
      case 'status':
      case 'info_time':
      case 'info_user':
      case 'info_text':
      case 'ical_uid':
      case 'ical_sequence':
      case 'ical_recur_id':
      case 'allow_registration':
      case 'registrant_limit':
      case 'registrant_limit_enabled':
      case 'registration_opens':
      case 'registration_opens_enabled':
      case 'registration_closes':
      case 'registration_closes_enabled':
        break;

      case 'description':
        // Description
        $class = ($keep_private & !empty($is_private_field['entry.description'])) ? "private" : "";
        $tbody .= create_details_row(get_vocab("description"), $data['description'], $as_html, $class);
        break;

      case 'confirmation_status':
        // Confirmation status
        if ($confirmation_enabled)
        {
          $value = ($data['tentative']) ? get_vocab("tentative") : get_vocab("confirmed");
          $tbody .= create_details_row(get_vocab("confirmation_status"), $value, $as_html);
        }
        break;

      case 'approval_status':
        // Approval status
        if ($approval_enabled)
        {
          $value = ($data['awaiting_approval']) ? get_vocab("awaiting_approval") : get_vocab("approved");
          $tbody .= create_details_row(get_vocab("approval_status"), $value, $as_html);
        }
        break;

      case 'room_id':
        // Room
        $value = $data['area_name'] . " - " . $data['room_name'];
        if ($room_disabled)
        {
          $value .= " (" . get_vocab("disabled") . ")";
        }
        $tbody .= create_details_row(get_vocab("room"), $value, $as_html);
        break;

      case 'start_time':
        // Start date
        if ($enable_periods)
        {
          list($start_period, $start_date) =  period_date_string($data['start_time'], $data['area_id']);
        }
        else
        {
          $start_date = time_date_string($data['start_time']);
        }
        $tbody .= create_details_row(get_vocab("start_date"), $start_date, $as_html);
        // Duration
        $tbody .= create_details_row(get_vocab("duration"), $data['duration'] . " " . get_vocab($data['dur_units']), $as_html);
        break;

      case 'end_time':
        // End date
        if ($enable_periods)
        {
          list( , $end_date) =  period_date_string($data['end_time'], $data['area_id'], -1);
        }
        else
        {
          $end_date = time_date_string($data['end_time']);
        }
        $tbody .= create_details_row(get_vocab("end_date"), $end_date, $as_html);
        break;

      case 'type':
        // Type
        if (isset($booking_types) && (count($booking_types) > 1))
        {
          $class = ($keep_private && !empty($is_private_field['entry.type'])) ? "private" : "";
          $tbody .= create_details_row(get_vocab("type"), $data['type'], $as_html, $class);
        }
        break;

      case 'create_by':
        // Created by
        $class = ($keep_private && !empty($is_private_field['entry.create_by'])) ? "private" : "";
        $tbody .= create_details_row(get_vocab("createdby"), $data['create_by'], $as_html, $class, $email[$key]);
        break;

      case 'modified_by':
        // Modified by
        $class = ($keep_private && !empty($is_private_field['entry.modified_by'])) ? "private" : "";
        $tbody .= create_details_row(get_vocab("modifiedby"), $data['modified_by'], $as_html, $class, $email[$key]);
        break;

      case 'timestamp':
        // Last updated
        $tbody .= create_details_row(get_vocab("lastupdate"), time_date_string($data['last_updated']), $as_html);
        break;

      default:

        // The custom fields
        foreach ($fields as $field)
        {
          // Get this field
          if ($field['name'] == $key)
          {
            break;
          }
        }

        // The field doesn't exist.
        if ($field['name'] != $key)
        {
          break;
        }

        $label = get_loc_field_name(_tbl('entry'), $key);
        // Output a yes/no if it's a boolean or integer <= 2 bytes (which we will
        // assume are intended to be booleans)
        if (($field['nature'] == 'boolean') ||
            (($field['nature'] == 'integer') && isset($field['length']) && ($field['length'] <= 2)) )
        {
          if ($keep_private && isset($is_private_field["entry.$key"]) && $is_private_field["entry.$key"])
          {
            $value = $data[$key];  // Will have been set previously
          }
          else
          {
            $value = empty($data[$key]) ? get_vocab("no") : get_vocab("yes");
          }
        }
        // Otherwise output a string
        else
        {
          if (isset($data[$key]))
          {
            // If the custom field is an associative array then we want
            // the value rather than the array key
            if (isset($select_options["entry.$key"]) &&
                is_assoc($select_options["entry.$key"]) &&
                array_key_exists($data[$key], $select_options["entry.$key"]))
            {
              $value = $select_options["entry.$key"][$data[$key]];
            }
            else
            {
              $value = $data[$key];
            }
          }
          else
          {
            $value = "";
          }
        }
        $class = ($keep_private && isset($is_private_field["entry.$key"]) && $is_private_field["entry.$key"]) ? "private" : "";
        $tbody .= create_details_row($label, $value, $as_html, $class);
        break;
    }  // switch
  }  // foreach

  // Repeat type
  $tbody .= create_details_row(get_vocab("rep_type"), get_vocab("rep_type_" . $data['rep_type']), $as_html);
  
if (isset($data['groups']) && is_array($data['groups']) && count($data['groups']) > 0)
{
    $representatives = [];
    $participant_emails = [];
    $participant_display_map = [];

    // Separate representatives and participants
    foreach ($data['groups'] as $group)
    {
        $full_name = trim($group['full_name']);
        $email = trim($group['email']);

        if (!empty($full_name)) {
            $representatives[] = htmlspecialchars($full_name);
        } elseif (!empty($email)) {
            $participant_emails[] = $email;
        }
    }

    // Output representatives
    if (!empty($representatives)) {
        $tbody .= create_details_row("Representative", implode(", ", $representatives), $as_html);
    }

    // Clean up participant emails
    $participant_emails = array_unique($participant_emails);

    if (!empty($participant_emails)) {
        $placeholders = rtrim(str_repeat('?,', count($participant_emails)), ',');
        $sql = "SELECT email, display_name FROM " . _tbl('users') . " WHERE email IN ($placeholders)";
        $rows = db()->sql_query($sql, $participant_emails);

        // Build email => display_name map
        foreach ($rows as $row) {
            $participant_display_map[$row['email']] = $row['display_name'];
        }

        // Build final participant list using display name if available
        $participant_names = [];
        foreach ($participant_emails as $email) {
            $name = $participant_display_map[$email] ?? $email;
            $participant_names[] = htmlspecialchars($name);
        }

        // Output participants in a box with <ul>
        $participant_html = '<div style="border: 1px solid #ccc; padding: 10px; border-radius: 6px; background-color: #f9f9f9;">';
        $participant_html .= '<ul style="margin: 0; padding-left: 20px;">';
        foreach ($participant_names as $name) {
            $participant_html .= '<li>' . $name . '</li>';
        }
        $participant_html .= '</ul></div>';

        $tbody .= create_details_row("Participants", $participant_html, true); // allow raw HTML
    }
}



  // Repeat details
  if($data['rep_type'] != REP_NONE)
  {
    switch ($data['rep_type'])
    {
      case REP_WEEKLY:
        // Repeat days
        $tbody .= create_details_row(get_vocab("rep_rep_day"), get_rep_day_list($data['rep_opt']), $as_html);
        break;
      case REP_MONTHLY:
        $tbody .= create_details_row(get_vocab("repeat_on"), get_monthly_repeat_day($data), $as_html);
        break;
      default:
        break;
    }

    // Repeat interval
    $value = $data['rep_interval'] . ' ' . get_rep_interval_units($data['rep_type'], $data['rep_interval']);
    $tbody .= create_details_row(get_vocab("rep_interval"), $value, $as_html);

    // Repeat end date
    $tbody .= create_details_row(
      get_vocab("rep_end_date"),
      datetime_format($datetime_formats['date'], $data['end_date']),
      $as_html
    );
  }

  $tbody .= ($as_html) ? "</tbody>\n" : "";

  return $tbody;
}

