using System.Text.RegularExpressions;

namespace XPLabs.Widget
{
    internal static class JsonTiny
    {
        public static string TryGetString(string json, string key, string defaultValue = "")
        {
            if (string.IsNullOrEmpty(json) || string.IsNullOrEmpty(key))
            {
                return defaultValue;
            }

            var pattern = "\"" + Regex.Escape(key) + "\"\\s*:\\s*\"((?:\\\\.|[^\"])*)\"";
            var m = Regex.Match(json, pattern, RegexOptions.IgnoreCase);
            if (!m.Success || m.Groups.Count < 2)
            {
                return defaultValue;
            }

            return Regex.Unescape(m.Groups[1].Value);
        }

        public static bool TryGetBool(string json, string key, bool defaultValue = false)
        {
            if (string.IsNullOrEmpty(json) || string.IsNullOrEmpty(key))
            {
                return defaultValue;
            }

            var pattern = "\"" + Regex.Escape(key) + "\"\\s*:\\s*(true|false)";
            var m = Regex.Match(json, pattern, RegexOptions.IgnoreCase);
            if (!m.Success || m.Groups.Count < 2)
            {
                return defaultValue;
            }

            var value = m.Groups[1].Value.ToLowerInvariant();
            return value == "true";
        }
    }
}

