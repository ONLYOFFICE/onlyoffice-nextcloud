Feature: Editor get user info

  Background:
    Given I am logged in as a regular user

  Scenario: Requesting info for an existing user returns their name and id
    Given a user with display name "Alice Tester" and an email exists
    When I request user info for that user
    Then the response should contain one user
    And that user's name should be "Alice Tester"

  Scenario: Requesting info for multiple users returns all of them
    Given a user with display name "Alice Tester" and an email exists
    And a user with display name "Bob Tester" and an email exists
    When I request user info for all created users
    Then the response should contain 2 users

  Scenario: A non-existent user is silently omitted
    When I request user info for "nonexistentuser"
    Then the response should be an empty list

  Scenario: A user without a custom avatar has no image field
    Given a user with display name "Alice Tester" and an email exists
    When I request user info for that user
    Then that user should not have an image field
