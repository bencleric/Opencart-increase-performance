**
 * Opencart Nga Nakakaon og Biko
 * Revision by Benie Mansueto Vison
 * Verssion 1.1.1
 * https://github.com/bencleric/opencart-turbo.git
 * 
 * Kini nga script moboost ni sa imong opencart, og dili ka mutuo. wala nakoy mahimo.
 * Bassaha nalang ang nausab sa ubos. Basin malingaaw diay ka
 * 1) eConvert ang imong MySQL DB Storage Engine from SAM to InnoDB.
 * 2) Mag-add ta og index sa tanan alien nga yabi
 * 3) English ang ubos para makasabot amg uban
 * 1) DONE: Convert MySQL DB Storage Engine from SAM to InnoDB
 * 2) DONE: Add indexes to all foreign keys
 * 3) TODO: Delete Script Function
 * 4) TODO: Replace config.php and admin/config.php with dynamic friendly version
 *
 * NOTES:
 * 1) This script should be deleted immediately following use
@@ -17,6 +19,15 @@

define('GITHUB_URL','https://github.com/bencleric/opencart-turbo.git');

/**
 * List of Additional Columns that should be indexed (in the format tablename.columname)
 * NOTE: Exclude any columns that end with '_id' here
 */
$index_list   = array();
$index_list[] = 'product.model';



$action = (!empty($_REQUEST['action'])) ? $_REQUEST['action'] : '';

if(file_exists('./config.php')) {
@@ -69,8 +80,8 @@
        <h3 class="panel-title">Available Options</h3>
      </div>
      <div class="panel-body">
        <a href="turbo.php?action=engine" class="btn btn-success btn-lg" onclick="return confirm('Sure ka nga atong econvert imo Opencart tables from SAM to InnoDB?');">Convert Database Engine</a> Change from SAM to InnoDB<br><br>
        <a href="turbo.php?acti
    </div>on=indexes" class="btn btn-success btn-lg">Add Database Indexes</a>
        <a href="turbo.php?action=engine" class="btn btn-success btn-lg" onclick="return confirm('Sure ka nga atong econvert imo Opencart database tables from SAM to InnoDB?');">Convert Database Engine</a><br><br>
        <a href="turbo.php?action=indexes" class="btn btn-success btn-lg" onclick="return confirm('Sure ka E-index nato imohang Opencart database tables?');">Add Database Indexes</a>
      </div>

@@ -88,8 +99,10 @@
              turbo_table_indexes();
              break;
            case 'delete':
              // Nothing yet
              break;
            default:
              // Nothing yet
              break;
          }
          ?></p>
@@ -105,7 +118,7 @@


function turbo_table_indexes() {
  global $db;
  global $db, $index_list;

  $tables = turbo_get_tables(true);
  if($tables && count($tables) > 0) {
@@ -115,17 +128,48 @@ function turbo_table_indexes() {
    // Loop through Tables
    foreach($tables as $table_name => $table) {

      if($table_name == 'product_description') {
        echo '<pre>';
        var_dump($table);
        echo '</pre>';
      }
      // Loop through Columns
      foreach($table['indexes'] as $column_name => $index) {
      foreach($table['columns'] as $column_name => $column) {

        $has_index   = false;
        $needs_index = false;

        // If Column is a Primary Key and is NOT the first Primary Key and does not have an index, we need to add an index
        // If Column name ends with _id and does not already have an index, we need to add an index
//        if($index['']
        // Does this column need an index?
        if(substr($column_name, -3) == '_id') {
          // Column ends in '_id'
          $needs_index = true;
        }
        elseif(in_array($table_name.'.'.$column_name, $index_list)) {
          // This column exists in the manual index list
          $needs_index = true;
        }

        // Loop through the indexes for this column to determine if it has one already
        if($column['indexes'] && !empty($column['indexes'])) {

          foreach($column['indexes'] as $index) {

            if($index['position'] == 1) {
              // This column is in first position in an Index
              $has_index = true;
            }
          }
        }

        if(!$has_index && $needs_index) {
          // Has no Index and needs an Index
          $sql = "ALTER TABLE `{$table_name}` ADD INDEX (  `{$column_name}` )";
          if($output = $db->query($sql)) {
            turbo_log("{$table_name}.{$column_name} - Index Added",'success','SUCCESS');
          }
          else {
            turbo_log("{$table_name}.{$column_name} - Index Add Failed - ".$db->error,'danger','ERROR');
          }
        }
        elseif($needs_index) {
          // Needs an Index but already has one
          turbo_log("{$table_name}.{$column_name} - Index Already Exists",'info','INFO');
        }
      }
    }

@@ -181,32 +225,56 @@ function turbo_get_tables($getindexes=false) {
        $table               = array();
        $table['name']       = $row['TABLE_NAME'];
        $table['engine']     = $row['ENGINE'];
        $table['indexes']    = false;
        $table['columns']    = false;

        if($getindexes) {
          $sql = "SELECT *

          // Get indexes first
          $sqli = "SELECT *
                  FROM INFORMATION_SCHEMA.STATISTICS
                  WHERE TABLE_SCHEMA LIKE '".DB_DATABASE."'
                  AND TABLE_NAME LIKE '".$table['name']."'";
          if($rsc = $db->query($sql)) {

            $table['indexes'] = array();
            while($indexes = $rsc->fetch_assoc()) {

              $index            = array();
              $index['name']    = $indexes['COLUMN_NAME'];
              $index['key']     = $indexes['INDEX_NAME']; // PRI=Primary Key, UNI=Unique Index, MUL=Non-Unique Index
              $index['primary'] = false;
              if($index['key'] == 'PRIMARY') {
                // Store the position if this is a Primary Key
                $index['primary'] = $indexes['SEQ_IN_INDEX'];
              }
          $table['indexes'] = array();
          if($rsi = $db->query($sqli)) {

            while($indexes = $rsi->fetch_assoc()) {

              $index             = array();
              $index['name']     = $indexes['COLUMN_NAME'];
              $index['key']      = $indexes['INDEX_NAME'];
              $index['unique']   = ($indexes['NON_UNIQUE'] == 1) ? false : true; // Invert logic
              $index['position'] = $indexes['SEQ_IN_INDEX'];

              if(!isset($table['indexes'][$index['name']])) {
                $table['indexes'][$index['name']] = array();
              }
              $table['indexes'][$index['name']][] = $index;
            }
          }

          // Get Columns
          $sqlc = "SELECT *
                  FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA LIKE '".DB_DATABASE."'
                  AND TABLE_NAME LIKE '".$table['name']."'";

          if($rsc = $db->query($sqlc)) {

            $table['columns'] = array();
            while($columns = $rsc->fetch_assoc()) {

              $column            = array();
              $column['name']    = $columns['COLUMN_NAME'];
              $column['type']    = $columns['DATA_TYPE'];
              $column['indexes'] = false;

              if(isset($table['indexes'][$column['name']])) {
                // If there are any Indexes for this column, add to Array
                $column['indexes'] = $table['indexes'][$column['name']];
              }
              $table['columns'][$column['name']] = $column;
            }
          }
          else {
            turbo_log("No DB Columns Found in Table {$table['name']}",'danger','ERROR');
          }
