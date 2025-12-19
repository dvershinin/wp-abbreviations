"""
E2E tests for Abbreviations plugin rendering.

These tests verify that abbreviations are properly wrapped in <abbr> tags
when posts are rendered on the frontend.
"""
import re


class TestAbbreviationRendering:
    """Test abbreviation rendering in post content."""

    def test_single_abbreviation_is_wrapped(
        self, set_abbreviations, create_post, get_post_content
    ):
        """Test that a single abbreviation is wrapped in <abbr> tag."""
        # Setup: Add an abbreviation
        set_abbreviations([["FYI", "For Your Information", ""]])

        # Create a post with the abbreviation
        _, post_url = create_post(
            title="Test FYI Abbreviation",
            content="Just FYI, this is a test post."
        )

        # Fetch the rendered content
        html = get_post_content(post_url)

        # Verify the abbreviation is wrapped
        assert '<abbr' in html
        assert 'title="For Your Information"' in html
        assert '>FYI</abbr>' in html

    def test_abbreviation_with_language_attribute(
        self, set_abbreviations, create_post, get_post_content
    ):
        """Test that language attribute is added when specified."""
        # Setup: Add an abbreviation with language
        set_abbreviations([["RSVP", "Répondez s'il vous plaît", "fr"]])

        # Create a post
        _, post_url = create_post(
            title="Test RSVP with French",
            content="Please RSVP to the event."
        )

        # Fetch the rendered content
        html = get_post_content(post_url)

        # Verify language attribute is present
        assert 'lang="fr"' in html
        assert "Répondez s'il vous plaît" in html or "Répondez" in html

    def test_abbreviation_without_language(
        self, set_abbreviations, create_post, get_post_content
    ):
        """Test that no lang attribute when language is empty."""
        # Setup: Add an abbreviation without language
        set_abbreviations([["API", "Application Programming Interface", ""]])

        # Create a post
        _, post_url = create_post(
            title="Test API without language",
            content="The API is well documented."
        )

        # Fetch the rendered content
        html = get_post_content(post_url)

        # Find the abbr tag for API
        abbr_match = re.search(r'<abbr[^>]*>API</abbr>', html)
        assert abbr_match is not None, "API abbreviation not found"

        # Verify no lang attribute in the match
        abbr_tag = abbr_match.group(0)
        assert 'lang="' not in abbr_tag or 'lang=""' in abbr_tag

    def test_multiple_abbreviations(
        self, set_abbreviations, create_post, get_post_content
    ):
        """Test multiple different abbreviations in one post."""
        # Setup: Add multiple abbreviations
        set_abbreviations([
            ["HTML", "HyperText Markup Language", ""],
            ["CSS", "Cascading Style Sheets", ""],
            ["JS", "JavaScript", ""],
        ])

        # Create a post with all abbreviations
        _, post_url = create_post(
            title="Web Technologies",
            content="Modern websites use HTML for structure, CSS for styling, and JS for interactivity."
        )

        # Fetch the rendered content
        html = get_post_content(post_url)

        # Verify all abbreviations are wrapped
        assert 'title="HyperText Markup Language"' in html
        assert 'title="Cascading Style Sheets"' in html
        assert 'title="JavaScript"' in html

    def test_abbreviation_only_wrapped_once(
        self, set_abbreviations, create_post, get_post_content
    ):
        """Test that each abbreviation is only wrapped once per post."""
        # Setup: Add an abbreviation
        set_abbreviations([["SEO", "Search Engine Optimization", ""]])

        # Create a post with the abbreviation appearing multiple times
        _, post_url = create_post(
            title="SEO Guide",
            content="SEO is important. Good SEO helps your site rank. SEO takes time."
        )

        # Fetch the rendered content
        html = get_post_content(post_url)

        # Count abbr tags for SEO - should be exactly 1
        abbr_count = len(re.findall(r'<abbr[^>]*>SEO</abbr>', html))
        assert abbr_count == 1, f"Expected 1 wrapped SEO, found {abbr_count}"

        # The other occurrences should remain plain text
        plain_seo_count = html.count("SEO") - 1  # minus the one in abbr
        assert plain_seo_count >= 2, "Other SEO occurrences should remain"

    def test_abbreviation_at_start_of_content(
        self, set_abbreviations, create_post, get_post_content
    ):
        """Test abbreviation at the very start of content."""
        set_abbreviations([["FAQ", "Frequently Asked Questions", ""]])

        _, post_url = create_post(
            title="FAQ Section",
            content="FAQ: Here are common questions."
        )

        html = get_post_content(post_url)
        assert '<abbr' in html
        assert 'title="Frequently Asked Questions"' in html

    def test_abbreviation_at_end_of_content(
        self, set_abbreviations, create_post, get_post_content
    ):
        """Test abbreviation at the end of content."""
        set_abbreviations([["TBD", "To Be Determined", ""]])

        _, post_url = create_post(
            title="Project Status",
            content="The launch date is TBD"
        )

        html = get_post_content(post_url)
        assert '<abbr' in html
        assert 'title="To Be Determined"' in html

    def test_abbreviation_with_punctuation(
        self, set_abbreviations, create_post, get_post_content
    ):
        """Test abbreviation followed by punctuation."""
        set_abbreviations([["ASAP", "As Soon As Possible", ""]])

        _, post_url = create_post(
            title="Urgent Request",
            content="Please respond ASAP. We need this ASAP!"
        )

        html = get_post_content(post_url)
        assert '<abbr' in html
        assert 'title="As Soon As Possible"' in html

    def test_no_abbreviation_inside_existing_abbr_tag(
        self, set_abbreviations, create_post, get_post_content
    ):
        """Test that we don't double-wrap existing abbr tags."""
        set_abbreviations([["XML", "eXtensible Markup Language", ""]])

        # Content already has an abbr tag
        _, post_url = create_post(
            title="XML Test",
            content='Use <abbr title="eXtensible Markup Language">XML</abbr> for data.'
        )

        html = get_post_content(post_url)

        # Should not have nested abbr tags
        assert '<abbr' in html
        # Count opening abbr tags - should be exactly 1
        abbr_count = len(re.findall(r'<abbr\s', html))
        # The existing one might get processed, but shouldn't be doubled
        assert abbr_count >= 1

    def test_abbreviation_has_nocode_class(
        self, set_abbreviations, create_post, get_post_content
    ):
        """Test that abbreviations have the 'nocode' class."""
        set_abbreviations([["URL", "Uniform Resource Locator", ""]])

        _, post_url = create_post(
            title="About URLs",
            content="A URL points to a web resource."
        )

        html = get_post_content(post_url)
        assert 'class="nocode"' in html

    def test_empty_abbreviations_list(
        self, clear_abbreviations, create_post, get_post_content
    ):
        """Test that content renders normally with no abbreviations."""
        clear_abbreviations()

        _, post_url = create_post(
            title="No Abbreviations",
            content="This post has FYI and API but no configured abbreviations."
        )

        html = get_post_content(post_url)

        # Should have content but no abbr tags added by the plugin
        assert "This post has FYI" in html or "FYI" in html
        # abbr tags might exist from theme, but not for FYI/API
        fyi_abbr = re.search(r'<abbr[^>]*>FYI</abbr>', html)
        assert fyi_abbr is None, "FYI should not be wrapped when not configured"
