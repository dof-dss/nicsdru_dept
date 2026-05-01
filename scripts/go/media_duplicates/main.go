// Selects all of the duplicate checksums in the media_field_data table
// and groups them before inserting into a custom table.

package main

import (
	"database/sql"
	"fmt"
	"os"
	"strings"

	_ "github.com/go-sql-driver/mysql"
)

func main() {

	if len(os.Args) < 2 {
		fmt.Println("Usage: mediaduplicates \"user:pass@tcp(host:port)/db\"")
		return
	}

	dsn := os.Args[1]

	db, err := sql.Open("mysql", dsn)

	if err != nil {
		panic(err)
	}
	defer db.Close()

	// Create the duplicates table.
	createTableQuery := `
    	CREATE TABLE IF NOT EXISTS media_duplicates (
    		id INT AUTO_INCREMENT PRIMARY KEY,
    		group_id INT NOT NULL,
    		mid INT NOT NULL,
    		checksum VARCHAR(255) NOT NULL,
    		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    	)`
	_, err = db.Exec(createTableQuery)
	if err != nil {
		panic(err)
	}

	// Find duplciate groups and ignore the bundles we haven't hashed.
	query := `
    		SELECT duplicates_checksum, GROUP_CONCAT(mid) as mids
    		FROM media_field_data
    		WHERE bundle NOT IN ('remote_video', 'secure_file') AND duplicates_checksum IS NOT NULL
    		GROUP BY duplicates_checksum
    		HAVING COUNT(*) > 1`

	rows, err := db.Query(query)
	if err != nil {
		panic(err)
	}
	defer rows.Close()

	// Loop through the results and inject into the duplicates table
	group_id, total_rows := 0, 0
	for rows.Next() {
		// Using NullString instead of string in case we have any media items without a hash.
		var checksum sql.NullString
		var mids_string sql.NullString

		// Fetch the checksum and ID for this row.
		err := rows.Scan(&checksum, &mids_string)
		if err != nil {
			panic(err)
		}

		if mids_string.Valid && checksum.Valid {
			// Split the comma-separated string of mids.
			mids := strings.Split(mids_string.String, ",")

			for _, mid := range mids {
				_, err := db.Exec(
					"INSERT INTO media_duplicates (group_id, mid, checksum) VALUES (?, ?, ?)",
					group_id, mid, checksum.String,
				)
				if err != nil {
					fmt.Printf("Error inserting mid %s: %v\n", mid, err)
					continue
				}
			}
			fmt.Printf("Processed group %d: (%d items)\n", group_id, len(mids))
			group_id++
			total_rows += len(mids)
		}
	}

	fmt.Printf("Finished, %d duplicate groups and %d duplicate media items \n", group_id, total_rows)
}
