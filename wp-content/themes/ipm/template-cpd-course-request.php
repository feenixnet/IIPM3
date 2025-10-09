<?php
/*
Template Name: External Course Request
*/

if (!defined('ABSPATH')) { exit; }

global $wpdb;
if (is_user_logged_in()) {
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}users WHERE ID = %d",
        get_current_user_id()
    ));
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT mb.*, ms.name as name, ms.designation as designation FROM {$wpdb->prefix}test_iipm_members mb LEFT JOIN {$wpdb->prefix}memberships ms ON ms.id = membership_level WHERE user_id = %d",
        get_current_user_id()
    ));
    $profile = $wpdb->get_row($wpdb->prepare(
        "SELECT mp.*, o.name as employer_name FROM {$wpdb->prefix}test_iipm_member_profiles mp LEFT JOIN {$wpdb->prefix}test_iipm_organisations o ON mp.employer_id = o.id WHERE user_id = %d",
        get_current_user_id()
    ));
} else {
}

get_header();
?>

<div class="member-portal-page main-container">
    <div class="container">
        <?php if (!function_exists('add_success_notification')) { include_once get_template_directory() . '/includes/notification-system.php'; } ?>
        <div class="page-header">
            <div>
                <h1>CPD Accreditation Request</h1>
                <p>Provide details for a course you'd like added to our list.</p>
            </div>
        </div>

        <div class="cpd-course-request-form">
            <div class="registration-form-container">
                <div class="notice">
                    <p>
                        Please do not use this form for reaccreditations. Confirm / tick the correct format of event and complete all questions, as this will reduce requests for additional information. Supply sufficient information on the event in order for it to be reviewed; i.e. slides or a detailed breakdown of topics. As some topics in an event may not be relevant to a particular designation please also include the agenda or time spent on different topics as CPD may be applied proportionately if relevant to a designation. Your application can take up to 20 working days to be processed.
                    </p>
                    <p class="description">
                        Membership numbers consist of a letter followed by a space and up to 5 digits.
                    </p>
                </div>
                <form id="external-course-request-form" class="registration-form" method="POST">
                    <!-- First row: Membership level (optional) | Email address -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="membership_level">Membership Level (optional)</label>
                            <input type="text" id="membership_level" name="membership_level" placeholder="e.g. Full Member" value="<?php echo $member->name; ?>" <?php echo is_user_logged_in() ? 'readonly' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label for="email_address">Email Address <span class="required-asterisk">*</span></label>
                            <input type="email" id="email_address" name="email_address" placeholder="you@example.com" value="<?php echo $user->user_email; ?>" <?php echo is_user_logged_in() ? 'readonly' : ''; ?> required>
                        </div>
                    </div>

                    <!-- Second row: First name | Surname -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required-asterisk">*</span></label>
                            <input type="text" id="first_name" name="first_name" placeholder="First name" value="<?php echo $profile->first_name; ?>" <?php echo is_user_logged_in() ? 'readonly' : ''; ?> required>
                        </div>
                        <div class="form-group">
                            <label for="sur_name">Surname <span class="required-asterisk">*</span></label>
                            <input type="text" id="sur_name" name="sur_name" placeholder="Surname" value="<?php echo $profile->sur_name; ?>" <?php echo is_user_logged_in() ? 'readonly' : ''; ?> required>
                        </div>
                    </div>

                    <!-- Third row: Organization (optional) -->
                    <div class="form-group">
                        <label for="organisation">Organization (optional)</label>
                        <input type="text" id="organisation" name="organisation" placeholder="Organization name" value="<?php echo $profile->employer_name; ?>" <?php echo is_user_logged_in() ? 'readonly' : ''; ?>>
                    </div>

                    <!-- Course Details section title (as per spec: "Coruse Details -> title") -->
                    <div class="form-section">
                        <h3>Course Details</h3>
                    </div>

                    <!-- Fifth row: Category -->
                    <div class="form-group">
                        <label for="course_category">Category <span class="required-asterisk">*</span></label>
                        <select id="course_category" name="course_category" required>
                            <option value="">Loading categories...</option>
                        </select>
                    </div>

                    <!-- Sixth row: Course title -->
                    <div class="form-group">
                        <label for="course_name">Course Title <span class="required-asterisk">*</span></label>
                        <input type="text" id="course_name" name="course_name" placeholder="Enter course title" required>
                    </div>

                    <!-- Seventh row: Course code -->
                    <div class="form-group">
                        <label for="LIA_Code">Course Code</label>
                        <input type="text" id="LIA_Code" name="LIA_Code" placeholder="Enter course code (if any)">
                    </div>

                    <!-- Eighth row: Course duration (hours, round to 0.5) -->
                    <div class="form-group">
                        <label for="course_cpd_mins">Course Duration (hours) <span class="required-asterisk">*</span></label>
                        <input type="number" id="course_cpd_mins" name="course_cpd_mins" min="0.5" step="0.5" placeholder="e.g. 1.5" required>
                    </div>

                    <!-- Ninth row: Declaration section title -->
                    <div class="form-section">
                        <h3>Declaration <span class="required-asterisk">*</span></h3>
                    </div>

                    <!-- Tenth row: Checkbox + long label -->
                    <div class="form-group">
                        <div class="credit-checkbox-container">
                            <input type="checkbox" id="declaration_agree" name="declaration_agree" required>
                            <p>
                                I understand and accept that any CPD credit awarded for the above event/training programme will be based on the information I have submitted in relation to the duration of the presentation(s) and the content. Should either of these criteria change at any stage 
                                (e.g. presentations are shortened, content is altered, refreshment breaks incorporated etc.), I will advise IOB and re-apply for CPD credit. I also understand and accept that IOB will not stand over CPD hours awarded, where such changes have occurred after the 
                                award was given, and that IOB reserves the right to refuse CPD claims made by individuals attending an event that has been subject to such changes. We may disclose information about events to our trusted education partners and/or their representatives e.g. LIA, 
                                Insurance Institute, Compliance Institute etc. for joint accreditation or relevancy of event purposes.
                            </p>
                        </div>
                    </div>

                    <!-- Tenth row (continued in spec): Submission date (date picker) -->
                    <div class="form-group">
                        <label for="submission_date">Submission Date <span class="required-asterisk">*</span></label>
                        <input type="date" id="submission_date" name="submission_date" style="width: 300px;" required>
                    </div>

                    <!-- 11th row: Submit button centered -->
                    <div class="form-group submit-button-container">
                    <button type="button" id="submit_external_course" class="btn btn-primary">SUBMIT FORM</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .member-portal-page {

    }
    .page-header h1, .page-header p {
        color: white;
    }
    .cpd-course-request-form {
        display: grid;
        gap: 20px;
        margin: auto;
        max-width: 900px;
    }
    .notice {
        margin-top: 40px;
    }
    .notice p {
        margin-bottom: 30px;
        color: gray;
    }
    .description {
        margin-bottom: 10px;
    }
    .credit-checkbox-container {
        display: flex;
        justify-content: space-between;
        gap: 10px;
    }
    .credit-checkbox-container input {
        margin-top: 6px;
    }
    .credit-checkbox-container p {
        width: calc(100% - 30px);
        color: grey;
    }
    .form-section {
        margin-top: 40px;
    }
    .submit-button-container {
        width: 100%;
        display: flex;
        margin-top: 100px;
    }
    .submit-button-container button {
        margin: auto;
    }
    .required-asterisk {
        color: #e11d48; /* red */
        font-weight: 700;
    }
</style>

 <script>
// Expose Ajax config for this template
const IIPM_CPD_REQ = {
    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
    userId: <?php echo get_current_user_id() ?: 0; ?>,
    adminNonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>'
};

document.addEventListener('DOMContentLoaded', function() {
    // Prefill if user exists (logged in) using iipm_admin_get_user_details
    if (IIPM_CPD_REQ.userId > 0) {
        fetch(IIPM_CPD_REQ.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'iipm_admin_get_user_details',
                user_id: String(IIPM_CPD_REQ.userId),
                nonce: IIPM_CPD_REQ.adminNonce
            })
        })
        .then(r => r.json())
        .then(res => {
            if (!res || !res.success || !res.data) return;
            const d = res.data;
            const profile = d.profile_info || {};
            const basic = d.basic_info || {};
            const member = d.member_info || {};
            const org = d.organization_info || {};

            const membershipLevelInput = document.getElementById('membership_level');
            if (membershipLevelInput) {
                // membership_level is likely an ID; leave as-is if no name provided
                membershipLevelInput.value = member.membership_level || '';
            }

            const emailInput = document.getElementById('email_address');
            if (emailInput) {
                emailInput.value = profile.email_address || profile.correspondence_email || basic.user_email || '';
            }

            const firstNameInput = document.getElementById('first_name');
            if (firstNameInput) firstNameInput.value = profile.first_name || '';

            const surNameInput = document.getElementById('sur_name');
            if (surNameInput) surNameInput.value = profile.sur_name || '';

            const orgInput = document.getElementById('organization');
            if (orgInput) orgInput.value = org.name || '';
        })
        .catch(() => {});
    }

    // Load categories for everyone
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'iipm_get_course_categories' })
    })
    .then(r => r.json())
    .then(res => {
        const sel = document.getElementById('course_category');
        if (!sel) return;
        sel.innerHTML = '';
        if (res && res.success && Array.isArray(res.data.categories)) {
            sel.appendChild(new Option('Select category', ''));
            res.data.categories.forEach(cat => {
                sel.appendChild(new Option(cat.name || cat.category || 'Unnamed', cat.id || cat.name));
            });
        } else {
            sel.appendChild(new Option('No categories available', ''));
        }
    })
    .catch(() => {
        const sel = document.getElementById('course_category');
        if (sel) sel.innerHTML = '<option value="">Failed to load categories</option>';
    });

    // Submit request to backend
    document.getElementById('submit_external_course').addEventListener('click', function() {
        const payload = {
            membership_level: (document.getElementById('membership_level')?.value || '').trim(),
            email_address: (document.getElementById('email_address')?.value || '').trim(),
            first_name: (document.getElementById('first_name')?.value || '').trim(),
            sur_name: (document.getElementById('sur_name')?.value || '').trim(),
            organisation: (document.getElementById('organisation')?.value || document.getElementById('organization')?.value || '').trim(),
            course_category: document.getElementById('course_category')?.value,
            course_name: (document.getElementById('course_name')?.value || document.getElementById('course_title')?.value || '').trim(),
            LIA_Code: (document.getElementById('LIA_Code')?.value || document.getElementById('course_code')?.value || '').trim(),
            course_cpd_mins: document.getElementById('course_cpd_mins')?.value || document.getElementById('course_duration')?.value,
            declaration_agree: document.getElementById('declaration_agree')?.checked,
            submission_date: document.getElementById('submission_date')?.value
        };

        // Basic validation for key fields (with notification system)
        if (!payload.email_address || !payload.course_name || !payload.course_category) {
            notifications.error('Missing required fields', 'Please fill in Email Address, Course Category and Course Title.');
            return;
        }
        if (!payload.course_cpd_mins) {
            notifications.error('Missing duration', 'Please enter Course Duration.');
            return;
        }
        if (!payload.declaration_agree) {
            notifications.error('Declaration required', 'Please confirm the declaration before submitting.');
            return;
        }
        if (!payload.submission_date) {
            notifications.error('Submission date required', 'Please select a Submission Date.');
            return;
        }
        if (payload.course_cpd_mins && (parseFloat(payload.course_cpd_mins) % 0.5 !== 0)) {
            alert('Course duration must be in 0.5 hour increments.');
            return;
        }

        const btn = document.getElementById('submit_external_course');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Submitting...';

        fetch(IIPM_CPD_REQ.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'iipm_submit_course_request',
                ...payload
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res && res.success) {
                alert('Thank you! Your request has been submitted for review.');
                (document.getElementById('external-course-request-form'))?.reset?.();
            } else {
                alert('Submission failed: ' + (res?.data || 'Unknown error'));
            }
        })
        .catch(() => {
            alert('Submission failed. Please try again.');
            
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = originalText;
            if(IIPM_CPD_REQ.userId > 0) {
                window.location.href = '<?php echo home_url('/cpd-courses/'); ?>';
            } else {
                window.location.href = '<?php echo home_url('/'); ?>';
            }
        });
    });
});
</script>

<?php get_footer(); ?>


