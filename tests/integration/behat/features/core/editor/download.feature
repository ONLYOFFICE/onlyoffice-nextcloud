Feature: Editor download as

  Background:
    Given I am logged in as a regular user
    And I have the "demo.doc" asset file in my home folder

  Scenario: Downloading a file with format conversion returns the converted file
    When I download the file converting it to "docx"
    Then the download should succeed
    And the downloaded file should have the extension "docx"

  Scenario: Downloading a non-existent file does not serve a download
    When I download a file that does not exist
    Then no file should be served for download
