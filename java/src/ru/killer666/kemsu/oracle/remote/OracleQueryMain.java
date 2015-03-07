package ru.killer666.kemsu.oracle.remote;

import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStreamReader;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.ResultSet;
import java.sql.ResultSetMetaData;
import java.sql.SQLException;
import java.sql.Statement;
import java.text.Format;
import java.text.SimpleDateFormat;
import java.util.Date;

import com.google.gson.Gson;
import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.google.gson.JsonPrimitive;

public class OracleQueryMain
{
	public static String createArray(JsonArray fields, JsonArray data)
	{
		JsonObject jsonData = new JsonObject();

		jsonData.add("fields", fields);
		jsonData.add("data", data);

		return jsonData.toString();
	}

	public static void main(String[] args) throws SQLException, IOException
	{
		Gson gson = new Gson();

		BufferedReader br = new BufferedReader(new InputStreamReader(System.in));
		String query = gson.fromJson(br.readLine(), String.class);

		DriverManager.registerDriver(new oracle.jdbc.driver.OracleDriver());
		Connection conn = DriverManager.getConnection(Config.connectionLine, "stud" + args[0], "stud" + args[0]);
		Statement stmt = conn.createStatement();
		ResultSet rset = stmt.executeQuery(query);
		ResultSetMetaData rsmd = rset.getMetaData();

		int numberOfColumns = rsmd.getColumnCount();

		JsonArray fields = new JsonArray();

		for (int i = 1; i <= numberOfColumns; i++)
			fields.add(new JsonPrimitive(rsmd.getColumnName(i) + ": " + rsmd.getColumnTypeName(i)));

		JsonArray data = new JsonArray();

		while (rset.next())
		{
			JsonArray result = new JsonArray();
			data.add(result);

			for (int i = 1; i <= numberOfColumns; i++)
			{
				String type = rsmd.getColumnTypeName(i);

				if (type.equalsIgnoreCase("VARCHAR2"))
				{
					String out = rset.getString(i);
					result.add(new JsonPrimitive(rset.wasNull() ? "null" : out));
				}
				else if (type.equalsIgnoreCase("NUMBER"))
					result.add(new JsonPrimitive(rset.getInt(i)));
				else if (type.equalsIgnoreCase("DATE"))
				{
					Date out = rset.getDate(i);
					Format formatter = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");
					result.add(new JsonPrimitive(rset.wasNull() ? "null" : formatter.format(out)));
				}
				else
					result.add(new JsonPrimitive("Unknown type"));
			}
		}

		System.out.println(createArray(fields, data));
	}
}
