package com.edurole.auth;

import com.sun.net.httpserver.HttpServer;
import com.sun.net.httpserver.HttpHandler;
import com.sun.net.httpserver.HttpExchange;
import java.io.*;
import java.net.InetSocketAddress;
import java.sql.*;
import java.nio.charset.StandardCharsets;
import java.util.stream.Collectors;

public class AuthServer {
    
    // Database settings
    private static final String DB_URL = "jdbc:mysql://localhost:3306/edurole";
    private static final String DB_USER = "root";
    private static final String DB_PASSWORD = "";
    
    public static void main(String[] args) throws IOException {
        HttpServer server = HttpServer.create(new InetSocketAddress(8081), 0);
        
        server.createContext("/api/auth/login", new LoginHandler());
        server.setExecutor(null);
        server.start();
        
        System.out.println("✅ Java Auth Server running on port 8081");
        System.out.println("   Testing with: POST http://localhost:8081/api/auth/login");
    }
    
    static class LoginHandler implements HttpHandler {
        @Override
        public void handle(HttpExchange exchange) throws IOException {
            // Allow CORS
            exchange.getResponseHeaders().set("Access-Control-Allow-Origin", "*");
            exchange.getResponseHeaders().set("Access-Control-Allow-Methods", "POST, OPTIONS");
            
            if (exchange.getRequestMethod().equalsIgnoreCase("OPTIONS")) {
                sendResponse(exchange, 200, "");
                return;
            }
            
            if (!exchange.getRequestMethod().equalsIgnoreCase("POST")) {
                sendResponse(exchange, 405, "{\"error\":\"Only POST allowed\"}");
                return;
            }
            
            // Read the request body
            String body = new BufferedReader(
                new InputStreamReader(exchange.getRequestBody(), StandardCharsets.UTF_8))
                .lines()
                .collect(Collectors.joining("\n"));
            
            // Parse email and password from JSON
            String email = extractValue(body, "email");
            String password = extractValue(body, "password");
            
            // Authenticate against database
            String result = authenticateUser(email, password);
            sendResponse(exchange, 200, result);
        }
        
        private String authenticateUser(String email, String password) {
            try (Connection conn = DriverManager.getConnection(DB_URL, DB_USER, DB_PASSWORD)) {
                
                // Query database for user
                String sql = "SELECT user_id, full_name, email, password_hash, role, status FROM users WHERE email = ?";
                PreparedStatement stmt = conn.prepareStatement(sql);
                stmt.setString(1, email);
                ResultSet rs = stmt.executeQuery();
                
                if (rs.next()) {
                    String storedHash = rs.getString("password_hash");
                    String status = rs.getString("status");
                    int userId = rs.getInt("user_id");
                    String fullName = rs.getString("full_name");
                    String role = rs.getString("role");
                    
                    // Check if account is active
                    if (!status.equals("active")) {
                        return "{\"success\":false,\"error\":\"Account is deactivated\"}";
                    }
                    
                    // Check password (simple check for now - accepts 'password' or 'admin123' or 'mpamz123')
                    boolean passwordMatch = false;
                    if (password.equals("password") || password.equals("admin123") || password.equals("mpamz123")) {
                        passwordMatch = true;
                    }
                    
                    if (passwordMatch) {
                        return "{\"success\":true,\"user_id\":" + userId + ",\"full_name\":\"" + fullName + "\",\"email\":\"" + email + "\",\"role\":\"" + role + "\"}";
                    } else {
                        return "{\"success\":false,\"error\":\"Invalid password\"}";
                    }
                } else {
                    return "{\"success\":false,\"error\":\"User not found\"}";
                }
                
            } catch (SQLException e) {
                return "{\"success\":false,\"error\":\"Database error: " + e.getMessage() + "\"}";
            }
        }
        
        private String extractValue(String json, String key) {
            String search = "\"" + key + "\":\"";
            int start = json.indexOf(search);
            if (start == -1) return "";
            start += search.length();
            int end = json.indexOf("\"", start);
            if (end == -1) return "";
            return json.substring(start, end);
        }
        
        private void sendResponse(HttpExchange exchange, int code, String response) throws IOException {
            exchange.getResponseHeaders().set("Content-Type", "application/json");
            exchange.sendResponseHeaders(code, response.getBytes().length);
            OutputStream os = exchange.getResponseBody();
            os.write(response.getBytes());
            os.close();
        }
    }
}