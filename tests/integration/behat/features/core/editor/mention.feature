Feature: Editor mention users

  Background:
    Given I am logged in as a regular user
    And I have a "docx" file in my home folder

  Scenario: Mentioning a user sends a notification successfully
    Given a user with display name "Alice Tester" and an email exists
    When I mention that user in the document with comment "Hello +alice@example.com check this"
    Then the mention should be sent successfully

  Scenario: Mentioning a user who has no access to the file shares it with them
    Given a user with display name "Alice Tester" and an email exists
    When I mention that user in the document with comment "Hello +alice@example.com check this"
    Then the file should be shared with that user
