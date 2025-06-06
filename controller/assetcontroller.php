<?php
require_once 'controller.php';
// require_once 'db.php';
class Asset extends Controller
{
    /**
     * Retrieve a setting value from the database or configuration.
     * For demonstration, this uses a simple query from a settings table.
     * Adjust the implementation as needed for your application.
     */
    protected function getSetting($key)
    {
        $this->setStatement("SELECT value FROM settings WHERE `key` = ?");
        $this->statement->execute([$key]);
        $value = $this->statement->fetchColumn();
        return $value !== false ? $value : null;
    }

    function retrieveAssets()
    {
        $this->setStatement("SELECT A.*, C.category_name, SC.sub_category_name, A.type_id, A.brand, T.type_name, 
       CO.asset_condition_name, A.status_id, S.status_name,A.file 
		FROM itam_asset A
		LEFT JOIN itam_asset_category C ON A.category_id = C.category_id
		LEFT JOIN itam_asset_sub_category SC ON A.sub_category_id = SC.sub_category_id
		LEFT JOIN itam_asset_type T ON A.type_id = T.type_id
		LEFT JOIN itam_asset_condition CO ON A.asset_condition_id = CO.asset_condition_id
		LEFT JOIN itam_asset_status S ON A.status_id = S.status_id;");

        $this->statement->execute();
        $this->sendJsonResponse($this->statement->fetchAll());
    }

    function retrieveOneAsset($id)
    {
        $this->setStatement("SELECT * FROM itam_asset WHERE asset_id = ?");
        $this->statement->execute([$id]);
        $result = $this->statement->fetch();
        $this->sendJsonResponse($result ?: ["error" => "Asset not found"], $result ? 200 : 404);
    }
    // // Retrieve a single sub-category by ID
    // function retrieveOneSubCategory($id)
    // {
    //     $this->setStatement("SELECT * FROM itam_asset_sub_category WHERE sub_category_id = ?");
    //     $this->statement->execute([$id]);
    //     $result = $this->statement->fetch();
    //     return $result;
    // }


    public function insertAsset($data)
    {
        extract($data);

        // // Ensure category_id exists
        // if (!isset($category_id)) {
        //     $this->sendJsonResponse(["error" => "Missing category_id"], 400);
        // }

        // Handle subcategory: Check if it exists, insert if missing
        if (!empty($sub_category_name)) {
            $this->setStatement("INSERT INTO itam_asset_sub_category (category_id, sub_category_name, code) VALUES (?, ?, ?)");
            $this->statement->execute([$category_id, $sub_category_name, strtoupper(substr($sub_category_name, 0, 2))]);
            $sub_category_id = $this->connection->lastInsertId();
        }
        if ($category_id == 2 && empty($sub_category_id)) {
            throw new Error("Di nagana");
        }

        // Generate asset name using the same method as the other function
        $this->setStatement("SELECT COUNT(*) as count FROM itam_asset WHERE sub_category_id = ? AND category_id = ? AND type_id is NULL");
        $this->statement->execute([$sub_category_id, $category_id]);
        $count = $this->statement->fetchColumn(0);
        $count += 1;

        if ($category_id === 1) {
            $asset_name = substr($asset_name, 0, 2) . "-" . $category_id . str_pad($count, 4, "0", STR_PAD_LEFT);
        } else {
            if ($sub_category_id) {
                $this->setStatement("SELECT code FROM itam_asset_sub_category WHERE sub_category_id = ?");
                $this->statement->execute([$sub_category_id]);
                $subcategory_code = $this->statement->fetchColumn() ?: "SC"; // Default if missing
                $asset_name = $subcategory_code . "-" . $category_id;
            }

            if ($type_id === "") {
                $asset_name .= str_pad($count, 4, "0", STR_PAD_LEFT);
            } else {
                $asset_name .= $type_id . str_pad($count, 3, "0", STR_PAD_LEFT);
            }
        }
        $insurance_id = null; // Initialize insurance_id variable
        if (!empty($insurance_coverage) && !empty($insurance_date_from) && !empty($insurance_date_to)) {
            $this->setStatement("INSERT INTO itam_asset_insurance (insurance_coverage, insurance_date_from, insurance_date_to) VALUES (?, ?, ?)");
            $insuranceSuccess = $this->statement->execute([
                $insurance_coverage,
                $insurance_date_from,
                $insurance_date_to
            ]);

            // Get the insurance_id if insurance was successfully inserted
            if ($insuranceSuccess) {
                $insurance_id = $this->connection->lastInsertId();
            }
        }
        // Step 1: Load dynamic settings
$maxImages = (int) $this->getSetting('max_images_per_item');
$allowedTypes = explode(',', $this->getSetting('allowed_file_types')); // e.g. 'jpg,jpeg,png,webp'

// Step 2: Validate uploaded images
if (!isset($_FILES['images'])) {
    $this->sendJsonResponse(["error" => "No image files uploaded."], 400);
}

$uploadedImages = $_FILES['images'];
$filenames = [];

// Step 3: Count images
$imageCount = count(array_filter($uploadedImages['name']));
if ($imageCount > $maxImages) {
    $this->sendJsonResponse(["error" => "Maximum of $maxImages images allowed."], 400);
}

// Step 4: Validate and save
for ($i = 0; $i < $imageCount; $i++) {
    $tmpName = $uploadedImages['tmp_name'][$i];
    $originalName = $uploadedImages['name'][$i];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedTypes)) {
        $this->sendJsonResponse(["error" => "File type .$ext is not allowed."], 400);
    }

    // Save file (ensure 'uploads/' folder exists with proper permissions)
    $newName = uniqid() . '.' . $ext;
    $destination = "uploads/" . $newName;

    if (move_uploaded_file($tmpName, $destination)) {
        $filenames[] = $destination;
    } else {
        $this->sendJsonResponse(["error" => "Failed to upload image: $originalName"], 500);
    }
}

        // Insert asset with file path
        $this->setStatement("INSERT INTO itam_asset (asset_name, serial_number, brand, category_id, sub_category_id, asset_condition_id, type_id, status_id, location, specifications, asset_amount, warranty_duration, warranty_due_date, purchase_date, notes, insurance_id, file) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $success = $this->statement->execute([
            $asset_name,
            $serial_number,
            $brand,
            $category_id,
            empty($sub_category_id) ? NULL : $sub_category_id,
            4,
            $type_id === "" ? null : $type_id,
            1,
            $location,
            $specifications,
            $asset_amount,
            $warranty_duration,
            $warranty_due_date,
            $purchase_date,
            $notes,
            $insurance_id === null ? null : $insurance_id, // Use the insurance_id if insurance exists
            implode(', ', $filenames)
        ]);


        $this->sendJsonResponse(["message" => $success ? "Asset added successfully" : "Failed to add asset"], $success ? 201 : 500);
    }




    function updateAsset($id, $data)
    {
        extract($data);
        $this->setStatement("UPDATE itam_asset 
            SET asset_name = ?, serial_number = ?, category_id = ?, sub_category_id = ?, type_id = ?, asset_condition_id = ?, status_id = ?, location = ?, specifications = ?, warranty_duration = ?, aging = ?, warranty_due_date = ?, purchase_date = ?, notes = ? 
            WHERE asset_id = ?");

        $success = $this->statement->execute([$asset_name, $serial_number, $category_id, $sub_category_id, $type_id, $asset_condition_id, $status_id, $location, $specifications, $warranty_duration, $aging, $warranty_due_date, $purchase_date, $notes, $id]);

        $this->sendJsonResponse(["message" => $success ? "Asset updated successfully" : "Failed to update asset"], $success ? 200 : 500);
    }

    function deleteAsset($id)
    {
        $this->setStatement("DELETE FROM itam_asset WHERE asset_id = ?");
        $success = $this->statement->execute([$id]);
        $this->sendJsonResponse(["message" => $success ? "Asset deleted successfully" : "Failed to delete asset"], $success ? 200 : 500);
    }

    /**
     * Get predefined repair urgency levels
     */
    function getRepairUrgencyLevels()
    {
        $this->setStatement("SELECT * FROM `itam_repair_urgency");
        $this->statement->execute([]);
        $result = $this->statement->fetchAll();
        $this->sendJsonResponse($result ?: ["error" => "Repair Urgency not found"], $result ? 200 : 404);
    }

    /**
     * Get assets with any repair urgency level (Critical, High, Medium, Low)
     */
    function getRepairUrgencyAssets()
    {
        $this->setStatement("SELECT A.asset_id, A.asset_name, C.category_name, SC.sub_category_name, 
                                    R.issue, R.remarks, R.urgency_id, U.urgency_level 
                             FROM itam_asset A
                             JOIN itam_asset_category C ON A.category_id = C.category_id
                             JOIN itam_asset_sub_category SC ON A.sub_category_id = SC.sub_category_id
                             JOIN itam_asset_repair_request R ON A.asset_id = R.asset_id
                             JOIN itam_repair_urgency U ON R.urgency_id = U.urgency_id
                             WHERE R.urgency_id IN (1, 2, 3, 4)  -- 1 = Critical, 2 = High, 3 = Medium, 4 = Low
                             ORDER BY R.urgency_id ASC");

        $this->statement->execute();
        $result = $this->statement->fetchAll();

        $this->sendJsonResponse($result ?: ["error" => "No assets with repair urgency found"], $result ? 200 : 404);
    }
    /**
     * Retrieve all asset conditions.
     */
    function getAssetCondition()
    {
        $this->setStatement("SELECT * FROM itam_asset_condition ORDER BY asset_condition_id ASC");
        $this->statement->execute();
        $result = $this->statement->fetchAll();
        $this->sendJsonResponse($result ?: ["error" => "No asset conditions found"], $result ? 200 : 404);
    }

    /**
     * Retrieve all asset statuses.
     */
    function getAssetStatus()
    {
        $this->setStatement("SELECT * FROM itam_asset_status ORDER BY status_id ASC");
        $this->statement->execute();
        $result = $this->statement->fetchAll();
        $this->sendJsonResponse($result ?: ["error" => "No asset statuses found"], $result ? 200 : 404);
    }
}
