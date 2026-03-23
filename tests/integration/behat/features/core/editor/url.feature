Feature: Editor file URL

  Background:
    Given I am logged in as a regular user
    And I have a "docx" file in my home folder

  Scenario: Requesting a URL for an existing file returns a download URL and file type
    When I request the URL for the file
    Then the response should contain a download URL
    And the file type in the response should be "docx"

  Scenario: Requesting a URL for a non-existent file returns an error
    When I request the URL for a file that does not exist
    Then the URL request should fail
