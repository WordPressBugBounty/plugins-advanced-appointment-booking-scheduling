<?php
global $wpdb;
$staff_table = $wpdb->prefix . 'abp_staff';
$services_table = $wpdb->prefix . 'appointment_services';
$staff_services = $wpdb->prefix . 'abp_staff_services';

// relationship table
$wpdb->query("CREATE TABLE IF NOT EXISTS $staff_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    service_id INT NOT NULL,
    INDEX (staff_id),
    INDEX (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

function abp_get_all_services()
{
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}appointment_services");
}

function abp_get_services_for_staff($staff_id)
{
    global $wpdb;
    return $wpdb->get_col($wpdb->prepare("SELECT service_id FROM {$wpdb->prefix}abp_staff_services WHERE staff_id = %d", $staff_id));
}

if (isset($_POST['abp_add_staff']) || isset($_POST['abp_update_staff'])) {
    check_admin_referer('abp_add_staff_action', 'abp_add_staff_nonce');

    $name = sanitize_text_field($_POST['staff_name']);
    $email = sanitize_email($_POST['staff_email']);
    $phone = sanitize_text_field($_POST['staff_phone']);
    $services = isset($_POST['staff_services']) ? array_map('intval', $_POST['staff_services']) : [];

    if (isset($_POST['staff_id']) && $_POST['staff_id']) {
        $staff_id = intval($_POST['staff_id']);

        $wpdb->update($staff_table, [
            'name' => $name,
            'email' => $email,
            'phone' => $phone
        ], ['id' => $staff_id]);

        $wpdb->delete($staff_services, ['staff_id' => $staff_id]);
        foreach ($services as $service_id) {
            $wpdb->insert($staff_services, [
                'staff_id' => $staff_id,
                'service_id' => $service_id
            ]);
        }

        echo '<div class="updated notice is-dismissible"><p>Staff updated successfully.</p></div>';

        unset($_GET['edit_staff']);
        $edit_staff = null;
        $edit_services = [];


    } else {
        $wpdb->insert($staff_table, [
            'name' => $name,
            'email' => $email,
            'phone' => $phone
        ]);

        $staff_id = $wpdb->insert_id;

        foreach ($services as $service_id) {
            $wpdb->insert($staff_services, [
                'staff_id' => $staff_id,
                'service_id' => $service_id
            ]);
        }

        echo '<div class="updated notice is-dismissible"><p>Staff added successfully.</p></div>';
        unset($_GET['edit_staff']);
        $edit_staff = null;
        $edit_services = [];
    }
}

// Handle delete
if (isset($_GET['delete_staff']) && is_numeric($_GET['delete_staff'])) {
    $staff_id = intval($_GET['delete_staff']);
    $wpdb->delete($staff_services, ['staff_id' => $staff_id]);
    $wpdb->delete($staff_table, ['id' => $staff_id]);
    echo '<div class="updated notice is-dismissible"><p>Staff deleted successfully.</p></div>';
}




$edit_staff = null;
$edit_services = [];
if (isset($_GET['edit_staff']) && is_numeric($_GET['edit_staff'])) {
    $edit_id = intval($_GET['edit_staff']);
    $edit_staff = $wpdb->get_row($wpdb->prepare("SELECT * FROM $staff_table WHERE id = %d", $edit_id));
    $edit_services = abp_get_services_for_staff($edit_id);
}

$all_services = abp_get_all_services();
$staff_members = $wpdb->get_results("SELECT * FROM $staff_table ORDER BY id DESC");
?>

<div class="wrap">
    <h2><?php echo $edit_staff ? 'Edit Staff' : 'Add New Staff'; ?></h2>
    <form method="post" class="abp-staff-form" style="max-width: 600px;">
        <?php wp_nonce_field('abp_add_staff_action', 'abp_add_staff_nonce'); ?>
        <?php if ($edit_staff): ?>
            <input type="hidden" name="staff_id" value="<?php echo esc_attr($edit_staff->id); ?>">
        <?php endif; ?>
        <input type="hidden" name="current_tab" value="<?php echo esc_attr($_GET['tab'] ?? 'appointments'); ?>">



        <table class="form-table">
            <tr>
                <th><label for="staff_name">Name</label></th>
                <td><input type="text" name="staff_name" id="staff_name" class="regular-text" required
                        value="<?php echo esc_attr($edit_staff->name ?? ''); ?>"></td>
            </tr>
            <tr>
                <th><label for="staff_email">Email</label></th>
                <td><input type="email" name="staff_email" id="staff_email" class="regular-text" required
                        value="<?php echo esc_attr($edit_staff->email ?? ''); ?>"></td>
            </tr>
            <tr>
                <th><label for="staff_phone">Phone</label></th>
                <td><input type="text" name="staff_phone" id="staff_phone" class="regular-text"
                        value="<?php echo esc_attr($edit_staff->phone ?? ''); ?>"></td>
            </tr>
            <tr>
                <th><label for="staff_services">Assign Services</label></th>
                <td>
                    <select name="staff_services[]" id="staff_services" multiple
                        style="min-width:300px; height: 100px;">
                        <?php foreach ($all_services as $service): ?>
                            <option value="<?php echo esc_attr($service->id); ?>" <?php selected(in_array($service->id, $edit_services)); ?>>
                                <?php echo esc_html($service->service_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Hold Ctrl (Windows) or Command (Mac) to select multiple options.</p>
                </td>
            </tr>
        </table>

        <p>
            <input type="submit" name="<?php echo $edit_staff ? 'abp_update_staff' : 'abp_add_staff'; ?>"
                class="button button-primary" value="<?php echo $edit_staff ? 'Update Staff' : 'Add Staff Member'; ?>">
        </p>
    </form>

    <hr>

    <h2>All Staff Members</h2>
    <?php if (!empty($staff_members)): ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Services</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff_members as $staff):
                    $assigned_services = abp_get_services_for_staff($staff->id);
                    $service_names = array_filter(array_map(function ($sid) use ($all_services) {
                        foreach ($all_services as $srv) {
                            if ((int) $srv->id === (int) $sid)
                                return $srv->service_name;
                        }
                        return null;
                    }, $assigned_services));
                    ?>
                    <tr>
                        <td><?php echo esc_html($staff->id); ?></td>
                        <td><?php echo esc_html($staff->name); ?></td>
                        <td><?php echo esc_html($staff->email); ?></td>
                        <td><?php echo esc_html($staff->phone); ?></td>
                        <td><?php echo esc_html(implode(', ', $service_names)); ?></td>
                        <td><?php echo esc_html($staff->created_at); ?></td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg(['tab' => 'staff', 'edit_staff' => $staff->id])); ?>"
                                class="button">Edit</a>
                            <a href="<?php echo esc_url(add_query_arg(['tab' => 'staff', 'delete_staff' => $staff->id])); ?>"
                                class="button button-small"
                                onclick="return confirm('Are you sure you want to delete this staff member?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No staff members found.</p>
    <?php endif; ?>
</div>