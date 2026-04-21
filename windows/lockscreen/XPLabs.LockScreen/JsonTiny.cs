using System;
using System.Text.RegularExpressions;

namespace XPLabs.LockScreen
{
    // Tiny JSON extractor to avoid extra dependencies.
    internal static class JsonTiny
    {
        public static bool TryGetBool(string json, string key, bool defaultValue)
        {
            var m = Regex.Match(json ?? "", $"\"{Regex.Escape(key)}\"\\s*:\\s*(true|false)", RegexOptions.IgnoreCase);
            if (!m.Success) return defaultValue;
            return string.Equals(m.Groups[1].Value, "true", StringComparison.OrdinalIgnoreCase);
        }

        public static string TryGetString(string json, string key, string defaultValue)
        {
            var m = Regex.Match(json ?? "", $"\"{Regex.Escape(key)}\"\\s*:\\s*\"([^\"]*)\"", RegexOptions.IgnoreCase);
            if (!m.Success) return defaultValue;
            return m.Groups[1].Value ?? defaultValue;
        }
    }
}

