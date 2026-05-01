package main

/*
  Go script to generate a hash based on a file's contents.
  To compile for Linux run: GOOS=linux GOARCH=amd64 go build -o filehash .
*/


import (
	"crypto/sha256"
	"fmt"
	"io"
	"os"
)

func main() {
	if len(os.Args) != 2 {
		fmt.Fprintln(os.Stderr, "Usage: filehash <filepath>")
		os.Exit(0)
	}

	filePath := os.Args[1]

	file, err := os.Open(filePath)
	if err != nil {
		fmt.Fprintln(os.Stderr, "unable to open file:", err)
		os.Exit(2)
	}
	defer file.Close()

	hasher := sha256.New()
	if _, err := io.Copy(hasher, file); err != nil {
		fmt.Fprintln(os.Stderr, "unable to read file:", err)
		os.Exit(1)
	}

	fmt.Printf("%x\n", hasher.Sum(nil))
}
