using System;
using System.Text.RegularExpressions;

namespace XPLabs.LockScreen
{
    // Tiny JSON patcher to avoid extra dependencies.
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

        public static string SetBool(string json, string key, bool value)
        {
            json = string.IsNullOrWhiteSpace(json) ? "{}" : json;
            var literal = value ? "true" : "false";
            var pattern = $"\"{Regex.Escape(key)}\"\\s*:\\s*(true|false)";
            if (Regex.IsMatch(json, pattern, RegexOptions.IgnoreCase))
            {
                return Regex.Replace(json, pattern, $"\"{key}\": {literal}", RegexOptions.IgnoreCase);
            }

            return InsertProperty(json, $"\"{key}\": {literal}");
        }

        public static string SetString(string json, string key, string value)
        {
            json = string.IsNullOrWhiteSpace(json) ? "{}" : json;
            var escaped = EscapeJson(value ?? "");
            var pattern = $"\"{Regex.Escape(key)}\"\\s*:\\s*\"[^\"]*\"";
            if (Regex.IsMatch(json, pattern, RegexOptions.IgnoreCase))
            {
                return Regex.Replace(json, pattern, $"\"{key}\": \"{escaped}\"", RegexOptions.IgnoreCase);
            }

            return InsertProperty(json, $"\"{key}\": \"{escaped}\"");
        }

        private static string InsertProperty(string json, string property)
        {
            var trimmed = (json ?? "{}").Trim();
            if (trimmed == "{}") return "{" + property + "}";
            if (trimmed.EndsWith("}", StringComparison.Ordinal))
            {
                return trimmed.TrimEnd('}').TrimEnd().TrimEnd(',') + "," + property + "}";
            }

            return trimmed + "," + property;
        }

        private static string EscapeJson(string value)
        {
            return value
                .Replace("\\", "\\\\")
                .Replace("\"", "\\\"")
                .Replace("\r", "\\r")
                .Replace("\n", "\\n");
        }
    }
}
