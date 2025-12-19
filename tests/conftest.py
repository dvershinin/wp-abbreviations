"""
Pytest fixtures for Abbreviations plugin e2e tests.
"""
import os
import shlex
import subprocess
import time
from typing import Iterable, Union

import pytest
import requests


WP_URL = os.environ.get("WP_URL", "http://wordpress")
WP_PATH = "/var/www/html"


class WPCLIError(Exception):
    """Exception raised when WP-CLI command fails."""
    pass


def wp_cli(
    command: Union[str, Iterable[str]],
    capture_output: bool = True,
    input_data: str | None = None,
) -> str:
    """
    Execute a WP-CLI command inside the tester container pointing at WordPress.

    Args:
        command: WP-CLI subcommand (string or sequence of args)
        capture_output: Whether to capture and return output
        input_data: Optional string piped to STDIN

    Returns:
        Command output as string

    Raises:
        WPCLIError: If command fails
    """
    if isinstance(command, str):
        sub_args = shlex.split(command)
    else:
        sub_args = list(command)

    args = ["wp", f"--path={WP_PATH}", f"--url={WP_URL}", "--allow-root"]
    args.extend(sub_args)
    env = os.environ.copy()
    env.setdefault("WORDPRESS_DB_HOST", "db")
    env.setdefault("WORDPRESS_DB_USER", "wordpress")
    env.setdefault("WORDPRESS_DB_PASSWORD", "wordpress")
    env.setdefault("WORDPRESS_DB_NAME", "wordpress")

    result = subprocess.run(
        args,
        capture_output=capture_output,
        text=True,
        cwd=os.path.dirname(__file__),
        env=env,
        input=input_data,
    )
    if result.returncode != 0:
        raise WPCLIError(f"WP-CLI failed: {result.stderr}\n{result.stdout}")
    return result.stdout.strip()


@pytest.fixture(scope="session", autouse=True)
def ensure_wp_ready():
    """Ensure WordPress and WP-CLI are ready before running tests."""
    # Wait for WP-CLI to be able to connect
    for attempt in range(30):
        try:
            wp_cli("option get siteurl")
            break
        except WPCLIError:
            time.sleep(1)
    else:
        raise RuntimeError("WordPress not ready after 30 seconds")
    # Flush cache and clear any stale abbreviations option
    wp_cli(["eval", "wp_cache_flush(); delete_option('abbreviations');"])
    time.sleep(0.5)


@pytest.fixture(scope="session")
def wp_url():
    """Return the WordPress URL."""
    return WP_URL


@pytest.fixture(scope="session")
def wp_session():
    """Create a requests session for WordPress."""
    session = requests.Session()
    # Wait for WordPress to be ready
    for _ in range(30):
        try:
            response = session.get(f"{WP_URL}/", timeout=5)
            if response.status_code == 200:
                break
        except requests.RequestException:
            pass
        time.sleep(2)
    return session


@pytest.fixture
def set_abbreviations():
    """
    Factory fixture to set abbreviations in WordPress options.

    Usage:
        set_abbreviations([["FYI", "For Your Information", "en"]])
    """
    def _php_value(value):
        if isinstance(value, list):
            return "array(" + ", ".join(_php_value(item) for item in value) + ")"
        escaped = value.replace("\\", "\\\\").replace("'", "\\'")
        return f"'{escaped}'"

    def _set_abbreviations(abbreviations: list):
        """
        Set abbreviations option in WordPress.

        Args:
            abbreviations: List of [short, description, lang] lists
        """
        php_array = _php_value(abbreviations)
        # Delete first to ensure clean state, then set new value
        wp_cli(["eval", f"delete_option('abbreviations'); add_option('abbreviations', {php_array}); wp_cache_flush();"])
        # Give WordPress a moment to persist the option
        time.sleep(0.3)

    return _set_abbreviations


@pytest.fixture
def clear_abbreviations():
    """Clear all abbreviations from WordPress options."""
    def _clear():
        wp_cli(["eval", "update_option('abbreviations', array()); wp_cache_flush();"])
        time.sleep(0.2)
    return _clear


@pytest.fixture
def create_post():
    """
    Factory fixture to create a WordPress post.

    Returns:
        Tuple of (post_id, post_url)
    """
    created_posts = []

    def _create_post(title: str, content: str) -> tuple:
        """
        Create a post and return its ID and URL.

        Args:
            title: Post title
            content: Post content

        Returns:
            Tuple of (post_id, post_url)
        """
        # Escape content for shell
        escaped_content = content.replace("'", "'\\''")
        escaped_title = title.replace("'", "'\\''")

        post_id = wp_cli(
            f"post create --post_title='{escaped_title}' "
            f"--post_content='{escaped_content}' "
            f"--post_status=publish --porcelain"
        )
        post_url = wp_cli(f"post get {post_id} --field=url")
        created_posts.append(post_id)
        return post_id, post_url

    yield _create_post

    # Cleanup: delete created posts
    for post_id in created_posts:
        try:
            wp_cli(f"post delete {post_id} --force")
        except WPCLIError:
            pass


@pytest.fixture
def get_post_content(wp_session, wp_url):
    """
    Factory fixture to fetch rendered post content.

    Returns:
        Function that takes a URL and returns the page HTML
    """
    def _get_content(url: str) -> str:
        """
        Fetch the rendered HTML content of a page.

        Args:
            url: Full URL to fetch

        Returns:
            HTML content as string
        """
        response = wp_session.get(url, timeout=10)
        response.raise_for_status()
        return response.text

    return _get_content
